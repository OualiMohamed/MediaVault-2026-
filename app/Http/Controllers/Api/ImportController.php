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
        if (!in_array($type, $validTypes)) {
            return response()->json(['error' => 'Invalid type'], 422);
        }

        $request->validate([
            'file' => 'required|file|mimes:csv,json,txt|max:10240', // 10MB max
        ]);

        $file = $request->file('file');
        $content = file_get_contents($file->getPathname());

        // Fetch existing items for duplicate check
        $existingItems = CollectionItem::where('user_id', Auth::id())
            ->where('type', $type)
            ->get(['id', 'title', 'barcode']);

        $existingTitles = $existingItems->pluck('title')->map(fn($t) => strtolower($t))->toArray();
        $existingBarcodes = $existingItems->pluck('barcode', 'barcode')->filter()->toArray();

        $rows = $type === 'tv_show' ? $this->parseJson($content) : $this->parseCsv($content, $type);

        if ($rows === null) {
            return response()->json(['error' => 'Failed to parse file. Check the format.'], 422);
        }

        $validRows = [];
        $duplicates = 0;
        $errors = 0;
        $errorMessages = [];

        foreach ($rows as $index => $row) {
            $title = $row['title'] ?? null;
            $barcode = $row['barcode'] ?? null;

            if (empty($title)) {
                $errors++;
                $errorMessages[] = "Row " . ($index + 1) . ": Missing title.";
                continue;
            }

            // Check duplicates
            $isDuplicate = false;
            if (!empty($barcode) && isset($existingBarcodes[$barcode])) {
                $isDuplicate = true;
            } elseif (in_array(strtolower($title), $existingTitles)) {
                $isDuplicate = true;
            }

            if ($isDuplicate) {
                $duplicates++;
                continue;
            }

            // Basic type validation
            if (!$this->validateRowData($row, $type)) {
                $errors++;
                $errorMessages[] = "Row " . ($index + 1) . " ({$title}): Invalid data for required fields.";
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
            'items' => $validRows, // Send back to frontend for execution
        ]);
    }

    public function execute(Request $request, string $type)
    {
        $validTypes = ['movie', 'book', 'game', 'music', 'tv_show'];
        if (!in_array($type, $validTypes)) {
            return response()->json(['error' => 'Invalid type'], 422);
        }

        $request->validate([
            'items' => 'required|array|max:500',
            'items.*.title' => 'required|string|max:255',
        ]);

        $items = $request->items;
        $modelClass = $this->getModelClass($type);
        $inserted = 0;

        DB::transaction(function () use ($items, $type, $modelClass, &$inserted) {
            foreach ($items as $itemData) {
                $baseFields = ['title', 'barcode', 'purchase_date', 'purchase_price', 'condition', 'status', 'notes'];
                $detailData = [];

                foreach ($itemData as $key => $value) {
                    if (in_array($key, $baseFields)) {
                        continue; // Handled below
                    }
                    if ($value !== null && $value !== '') {
                        $detailData[$key] = $value;
                    }
                }

                // Handle booleans (Yes/No from CSV)
                if (isset($itemData['seen']))
                    $detailData['seen'] = $itemData['seen'] === true || strtolower($itemData['seen']) === 'yes';
                if (isset($itemData['read']))
                    $detailData['read'] = $itemData['read'] === true || strtolower($itemData['read']) === 'yes';
                if (isset($itemData['completed']))
                    $detailData['completed'] = $itemData['completed'] === true || strtolower($itemData['completed']) === 'yes';

                // Handle TV Show nested seasons
                if ($type === 'tv_show' && isset($itemData['seasons']) && is_array($itemData['seasons'])) {
                    $detailData['seasons'] = $itemData['seasons'];
                    unset($detailData['network'], $detailData['total_seasons']); // prevent mass assignment if not cast properly
                } elseif ($type === 'tv_show') {
                    // Map flat tv show details if they exist
                    foreach (['network', 'total_seasons', 'total_episodes', 'watch_status'] as $tf) {
                        if (isset($itemData[$tf]) && $itemData[$tf] !== '') {
                            $detailData[$tf] = $itemData[$tf];
                        }
                    }
                }

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

                $inserted++;
            }
        });

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