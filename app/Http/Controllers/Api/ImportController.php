<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CollectionItem;
use App\Models\Movie;
use App\Models\Book;
use App\Models\Game;
use App\Models\Music;
use App\Models\TvShow;
use App\Models\Franchise;
use App\Models\BookSeries;
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

        $request->validate(['file' => 'required|file|mimes:csv,json,zip|max:51200']);

        $file = $request->file('file');
        $isZip = $file->getClientOriginalExtension() === 'zip';

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

            $dataPath = $importSessionDir . '/data.json';
            if (!File::exists($dataPath)) {
                File::deleteDirectory($importSessionDir);
                return response()->json(['error' => 'ZIP file is missing data.json'], 422);
            }
            $content = File::get($dataPath);
        } else {
            $content = file_get_contents($file->getPathname());
        }

        $sessionToken = basename($importSessionDir);

        $existingItems = CollectionItem::where('user_id', Auth::id())->where('type', $type)->get(['id', 'title', 'barcode']);
        $existingTitles = $existingItems->pluck('title')->map(fn($t) => strtolower($t))->toArray();
        $existingBarcodes = $existingItems->pluck('barcode', 'barcode')->filter()->toArray();

        $trimmed = trim($content);
        if (str_starts_with($trimmed, '[') || str_starts_with($trimmed, '{')) {
            $rows = json_decode($content, true);
        } else {
            $rows = $this->parseCsv($content, $type);
            if (!$rows) {
                $rows = json_decode($content, true);
            }
        }

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

            // Flatten nested 'details' if from ZIP
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
            'session_token' => $sessionToken,
        ]);
    }

    public function execute(Request $request, string $type)
    {
        $validTypes = ['movie', 'book', 'game', 'music', 'tv_show'];
        if (!in_array($type, $validTypes))
            return response()->json(['error' => 'Invalid type'], 422);

        $request->validate([
            'items' => 'required|array|max:500',
            'items.*.title' => 'required|string',
            'session_token' => 'required|string',
        ]);

        $items = $request->items;
        $sessionDir = storage_path('app/temp/' . $request->session_token);
        $modelClass = $this->getModelClass($type);
        $inserted = 0;

        // Fields that should NEVER go into detailData
        $skipFields = [
            'title',
            'barcode',
            'purchase_date',
            'purchase_price',
            'condition',
            'status',
            'notes',
            '_old_id',
            'franchise_name',
            'franchise',     // Converted to franchise_id below
            'series_name',
            'series',           // Converted to series_id below
            'franchise_id',
            'series_id',       // Prevent raw IDs from ZIP
        ];

        DB::transaction(function () use ($items, $type, $modelClass, $sessionDir, &$inserted, $skipFields) {
            foreach ($items as $itemData) {
                $oldId = $itemData['_old_id'] ?? null;
                $detailData = [];

                // Separate detail fields from base/skip fields
                foreach ($itemData as $key => $value) {
                    if (in_array($key, $skipFields))
                        continue;
                    if ($value !== null && $value !== '') {
                        $detailData[$key] = $value;
                    }
                }

                // Handle booleans
                if (isset($detailData['seen']))
                    $detailData['seen'] = filter_var($detailData['seen'], FILTER_VALIDATE_BOOLEAN);
                if (isset($detailData['read']))
                    $detailData['read'] = filter_var($detailData['read'], FILTER_VALIDATE_BOOLEAN);
                if (isset($detailData['completed']))
                    $detailData['completed'] = filter_var($detailData['completed'], FILTER_VALIDATE_BOOLEAN);

                // Handle franchise — supports both CSV (franchise_name) and ZIP (franchise) keys
                $franchiseName = $itemData['franchise_name'] ?? $itemData['franchise'] ?? null;
                if (!empty($franchiseName)) {
                    $franchise = Franchise::firstOrCreate(
                        ['user_id' => Auth::id(), 'name' => trim($franchiseName)],
                    );
                    $detailData['franchise_id'] = $franchise->id;
                    $detailData['franchise_position'] = !empty($itemData['franchise_position'])
                        ? (int) $itemData['franchise_position']
                        : null;
                } else {
                    $detailData['franchise_id'] = null;
                    $detailData['franchise_position'] = null;
                }

                // Handle series (books) — supports both CSV (series_name) and ZIP (series) keys
                if ($type === 'book') {
                    $seriesName = $itemData['series_name'] ?? $itemData['series'] ?? null;
                    if (!empty($seriesName)) {
                        $series = BookSeries::firstOrCreate(
                            ['user_id' => Auth::id(), 'name' => trim($seriesName)],
                        );
                        $detailData['series_id'] = $series->id;
                        $detailData['series_position'] = !empty($itemData['series_position'])
                            ? (int) $itemData['series_position']
                            : null;
                    } else {
                        $detailData['series_id'] = null;
                        $detailData['series_position'] = null;
                    }
                }

                // Handle audio_format — convert comma string to JSON array
                if (isset($detailData['audio_format']) && is_string($detailData['audio_format'])) {
                    $formats = array_map('trim', explode(',', $detailData['audio_format']));
                    $detailData['audio_format'] = array_values(array_filter($formats));
                }
                // If already an array from ZIP, keep it
                if (isset($detailData['audio_format']) && is_array($detailData['audio_format'])) {
                    $detailData['audio_format'] = array_values($detailData['audio_format']);
                }

                // Handle actors — convert comma string to JSON array of objects
                if (isset($detailData['actors']) && is_string($detailData['actors'])) {
                    $names = array_map('trim', explode(',', $detailData['actors']));
                    $detailData['actors'] = array_values(array_map(
                        fn($n) => ['name' => $n],
                        array_filter($names)
                    ));
                }
                // If already array of objects from ZIP, keep it
                if (isset($detailData['actors']) && is_array($detailData['actors'])) {
                    $detailData['actors'] = array_values($detailData['actors']);
                }

                $title = mb_substr($itemData['title'], 0, 255);

                // Truncate long detail strings to prevent DB errors
                $stringFields = ['author', 'director', 'genre', 'imdb_id', 'video_quality', 'language', 'isbn', 'publisher', 'platform', 'artist', 'label', 'network', 'trailer_url'];
                foreach ($stringFields as $field) {
                    if (isset($detailData[$field]) && is_string($detailData[$field])) {
                        $detailData[$field] = mb_substr($detailData[$field], 0, 255);
                    }
                }

                $collectionItem = CollectionItem::create([
                    'user_id' => Auth::id(),
                    'type' => $type,
                    'title' => $title,
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

                // Map old ID to new ID for cover matching
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
        $content = str_replace("\xEF\xBB\xBF", '', $content);

        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $content);
        rewind($stream);

        $headers = fgetcsv($stream);
        if (!$headers)
            return null;

        $headers = array_map('trim', $headers);
        $columnMap = $this->getCsvColumnMap($type);
        $rows = [];

        while (($row = fgetcsv($stream)) !== false) {
            if (empty(array_filter($row)))
                continue;

            $mappedRow = [];
            foreach ($headers as $index => $header) {
                $field = $columnMap[strtolower($header)] ?? null;
                if ($field) {
                    $value = $row[$index] ?? null;
                    $mappedRow[$field] = $value === '' ? null : $value;
                }
            }

            // Fallback: if title wasn't mapped, use the first column
            if (empty($mappedRow['title']) && !empty($row[0])) {
                $mappedRow['title'] = $row[0];
            }

            $rows[] = $mappedRow;
        }

        fclose($stream);
        return count($rows) > 0 ? $rows : null;
    }
    private function getCsvColumnMap(string $type): array
    {
        $common = [
            'title' => 'title',
            'barcode' => 'barcode',
            'purchase date' => 'purchase_date',
            'purchase_price' => 'purchase_price',
            'purchase price' => 'purchase_price',
            'condition' => 'condition',
            'status' => 'status',
            'notes' => 'notes',
            'personal rating' => 'personal_rating',
            'personal_rating' => 'personal_rating',
            'release year' => 'release_year',
            'release_year' => 'release_year',
            'genre' => 'genre',
            'franchise' => 'franchise_name',
            'franchise name' => 'franchise_name',
            'franchise_position' => 'franchise_position',
            'franchise position' => 'franchise_position',
        ];

        $movie = [
            'format' => 'format',
            'director' => 'director',
            'runtime (min)' => 'runtime_minutes',
            'runtime_minutes' => 'runtime_minutes',
            'imdb id' => 'imdb_id',
            'imdb_id' => 'imdb_id',
            'video quality' => 'video_quality',
            'video_quality' => 'video_quality',
            'audio format' => 'audio_format',
            'audio_format' => 'audio_format',
            'language' => 'language',
            'actors' => 'actors',
            'trailer url' => 'trailer_url',
            'trailer_url' => 'trailer_url',
            'seen' => 'seen',
            'date seen' => 'date_seen',
            'date_seen' => 'date_seen',
        ];

        $book = [
            'author' => 'author',
            'isbn' => 'isbn',
            'publisher' => 'publisher',
            'pages' => 'page_count',
            'page_count' => 'page_count',
            'series' => 'series_name',
            'series name' => 'series_name',
            'series_position' => 'series_position',
            'series position' => 'series_position',
            'read' => 'read',
            'date finished' => 'date_finished',
            'date_finished' => 'date_finished',
        ];

        $game = [
            'platform' => 'platform',
            'format' => 'format',
            'publisher' => 'publisher',
            'completed' => 'completed',
            'completion date' => 'completion_date',
            'completion_date' => 'completion_date',
        ];

        $music = [
            'artist' => 'artist',
            'format' => 'format',
            'label' => 'label',
            'tracks' => 'track_count',
            'track_count' => 'track_count',
            'vinyl speed' => 'vinyl_speed',
            'vinyl_speed' => 'vinyl_speed',
        ];

        $typeFields = match ($type) {
            'movie' => $movie,
            'book' => $book,
            'game' => $game,
            'music' => $music,
            default => [],
        };

        return array_merge($common, $typeFields);
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