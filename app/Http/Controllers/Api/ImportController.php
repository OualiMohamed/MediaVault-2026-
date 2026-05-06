<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CollectionItem;
use App\Models\Movie;
use App\Models\Book;
use App\Models\Game;
use App\Models\Music;
use App\Models\TvShow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ImportController extends Controller
{
    private function getModelClass(string $type): string
    {
        return match ($type) {
            'movie' => Movie::class,
            'book' => Book::class,
            'game' => Game::class,
            'music' => Music::class,
            'tv_show' => TvShow::class,
            default => Movie::class,
        };
    }

    public function validate(Request $request, string $type)
    {
        $validTypes = ['movie', 'book', 'game', 'music', 'tv_show'];
        if (!in_array($type, $validTypes))
            return response()->json(['error' => 'Invalid type'], 422);

        // Allow csv, json, OR zip
        $request->validate(['file' => 'required|file|mimes:csv,json,zip|max:51200']); // 50MB limit for zips

        $file = $request->file('file');
        $isZip = $file->getClientOriginalExtension() === 'zip';

        // Create a temp directory for this import session
        $importSessionDir = storage_path('app/temp/import_' . Str::uuid());
        File::ensureDirectoryExists($importSessionDir);

        if ($isZip) {
            $zip = new \ZipArchive();
            if ($zip->open($file->getPathname()) !== true) {
                File::deleteDirectory($importSessionDir);
                return response()->json(['error' => 'Invalid or corrupted ZIP file.'], 422);
            }
            $zip->extractTo($importSessionDir);
            $zip->close();

            // Look for data.json inside the zip
            $dataPath = $importSessionDir . '/data.json';
            if (!File::exists($dataPath)) {
                File::deleteDirectory($importSessionDir);
                return response()->json(['error' => 'ZIP file is missing data.json'], 422);
            }
            $content = File::get($dataPath);
        } else {
            $content = file_get_contents($file->getPathname());
        }

        // Store the session directory path in the response so the frontend can pass it back to execute
        $sessionToken = basename($importSessionDir);

        $existingItems = CollectionItem::where('user_id', Auth::id())->where('type', $type)->get(['id', 'title', 'barcode']);
        $existingTitles = $existingItems->pluck('title')->map(fn($t) => strtolower($t))->toArray();
        $existingBarcodes = $existingItems->pluck('barcode', 'barcode')->filter()->toArray();

        // Parse JSON (works for standard JSON exports AND our new Zip exports)
        $rows = json_decode($content, true);
        if (!is_array($rows)) {
            File::deleteDirectory($importSessionDir);
            return response()->json(['error' => 'Failed to parse file data.'], 422);
        }

        $validRows = [];
        $duplicates = 0;
        $errors = 0;
        $errorMessages = [];

        foreach ($rows as $index => $row) {
            $title = $row['title'] ?? null;
            if (empty($title)) {
                $errors++;
                $errorMessages[] = "Row " . ($index + 1) . ": Missing title.";
                continue;
            }

            $barcode = $row['barcode'] ?? null;
            $isDuplicate = (!empty($barcode) && isset($existingBarcodes[$barcode])) || in_array(strtolower($title), $existingTitles);

            if ($isDuplicate) {
                $duplicates++;
                continue;
            }

            // Flatten nested 'details' if it came from the Full Backup Zip
            if (isset($row['details']) && is_array($row['details'])) {
                $row = array_merge($row, $row['details']);
                unset($row['details']);
            }

            if (!$this->validateRowData($row, $type)) {
                $errors++;
                $errorMessages[] = "Row " . ($index + 1) . " ({$title}): Invalid required fields.";
                continue;
            }

            $validRows[] = $row;
        }

        return response()->json([
            'total' => count($rows),
            'valid' => count($validRows),
            'duplicates' => $duplicates,
            'errors' => $errors,
            'error_messages' => $errorMessages,
            'items' => $validRows,
            'session_token' => $sessionToken, // Pass this to execute!
        ]);
    }

    public function execute(Request $request, string $type)
    {
        $validTypes = ['movie', 'book', 'game', 'music', 'tv_show'];
        if (!in_array($type, $validTypes))
            return response()->json(['error' => 'Invalid type'], 422);

        $request->validate([
            'items' => 'required|array|max:500',
            'items.*.title' => 'required|string|max:255',
            'session_token' => 'required|string', // Required to find extracted covers
        ]);

        $items = $request->items;
        $sessionDir = storage_path('app/temp/' . $request->session_token);
        $modelClass = $this->getModelClass($type);
        $inserted = 0;

        // Map old IDs to new IDs for cover matching
        $idMap = [];

        DB::transaction(function () use ($items, $type, $modelClass, $sessionDir, &$inserted, &$idMap) {
            $baseFields = ['title', 'barcode', 'purchase_date', 'purchase_price', 'condition', 'status', 'notes', '_old_id'];

            foreach ($items as $itemData) {
                $oldId = $itemData['_old_id'] ?? null;
                $detailData = [];

                foreach ($itemData as $key => $value) {
                    if (in_array($key, $baseFields))
                        continue;
                    if ($value !== null && $value !== '')
                        $detailData[$key] = $value;
                }

                // Handle booleans
                if (isset($detailData['seen']))
                    $detailData['seen'] = filter_var($detailData['seen'], FILTER_VALIDATE_BOOLEAN);
                if (isset($detailData['read']))
                    $detailData['read'] = filter_var($detailData['read'], FILTER_VALIDATE_BOOLEAN);
                if (isset($detailData['completed']))
                    $detailData['completed'] = filter_var($detailData['completed'], FILTER_VALIDATE_BOOLEAN);

                $collectionItem = CollectionItem::create([
                    'user_id' => Auth::id(),
                    'type' => $type,
                    'title' => $itemData['title'],
                    'barcode' => $itemData['barcode'] ?? null,
                    'purchase_date' => $itemData['purchase_date'] ?? null,
                    'purchase_price' => $itemData['purchase_price'] ?? null,
                    'condition' => $itemData['condition'] ?? 'near_mint',
                    'status' => $itemData['status'] ?? 'owned',
                    'notes' => $itemData['notes'] ?? null,
                ]);

                $modelClass::create([
                    'collection_item_id' => $collectionItem->id,
                    ...$detailData,
                ]);

                // If we have an old ID and a covers folder exists, move the cover
                if ($oldId && is_dir($sessionDir . '/covers')) {
                    $files = glob($sessionDir . '/covers/' . $oldId . '.*');
                    if (!empty($files)) {
                        $oldCoverPath = $files[0];
                        $ext = pathinfo($oldCoverPath, PATHINFO_EXTENSION);
                        $newCoverName = 'imported_' . $collectionItem->id . '_' . time() . '.' . $ext;

                        $newPath = storage_path('app/public/covers/' . $newCoverName);
                        File::ensureDirectoryExists(dirname($newPath));

                        if (File::copy($oldCoverPath, $newPath)) {
                            $collectionItem->update(['cover_image' => 'covers/' . $newCoverName]);
                        }
                    }
                }

                $inserted++;
            }
        });

        // CRITICAL: Cleanup the temp directory after successful import
        if (is_dir($sessionDir)) {
            File::deleteDirectory($sessionDir);
        }

        return response()->json([
            'message' => "Successfully imported {$inserted} items.",
            'count' => $inserted,
        ]);
    }

    private function parseCsv(string $content, string $type): ?array
    {
        // Remove BOM
        $content = str_replace("\xEF\xBB\xBF", '', $content);

        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $content);
        rewind($stream);

        $headers = fgetcsv($stream);
        if (!$headers)
            return null;

        $headers = array_map('trim', $headers);

        $mapping = $this->getCsvMapping($type);
        $rows = [];

        while (($row = fgetcsv($stream)) !== false) {
            if (empty(array_filter($row)))
                continue; // Skip blank lines

            $mappedRow = [];
            foreach ($mapping as $index => $field) {
                $value = $row[$index] ?? null;
                $mappedRow[$field] = $value === '' ? null : $value;
            }
            $rows[] = $mappedRow;
        }
        fclose($stream);
        return $rows;
    }

    private function parseJson(string $content): ?array
    {
        $data = json_decode($content, true);
        if (!is_array($data))
            return null;
        return $data;
    }

    private function getCsvMapping(string $type): array
    {
        return match ($type) {
            'movie' => ['title', 'format', 'director', 'genre', 'release_year', 'runtime_minutes', 'imdb_id', 'video_quality', 'audio_format', 'language', 'seen', 'date_seen', 'personal_rating', 'status', 'condition', 'purchase_date', 'purchase_price', 'barcode', 'notes'],
            'book' => ['title', 'author', 'isbn', 'publisher', 'page_count', 'genre', 'release_year', 'read', 'date_finished', 'personal_rating', 'status', 'condition', 'purchase_date', 'purchase_price', 'barcode', 'notes'],
            'game' => ['title', 'platform', 'format', 'genre', 'publisher', 'release_year', 'completed', 'completion_date', 'personal_rating', 'status', 'condition', 'purchase_date', 'purchase_price', 'barcode', 'notes'],
            'music' => ['title', 'artist', 'format', 'genre', 'label', 'track_count', 'vinyl_speed', 'release_year', 'personal_rating', 'status', 'condition', 'purchase_date', 'purchase_price', 'barcode', 'notes'],
            default => [],
        };
    }

    private function validateRowData(array $row, string $type): bool
    {
        if ($type === 'movie' && empty($row['format']))
            return false;
        if ($type === 'book' && empty($row['author']))
            return false;
        if ($type === 'game' && (empty($row['platform']) || empty($row['format'])))
            return false;
        if ($type === 'music' && (empty($row['artist']) || empty($row['format'])))
            return false;
        return true;
    }
}