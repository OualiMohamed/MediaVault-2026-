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
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\File;

class ExportController extends Controller
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

    private function getColumns(string $type): array
    {
        return match ($type) {
            'movie' => [
                'Title',
                'Format',
                'Director',
                'Genre',
                'Release Year',
                'Runtime (min)',
                'IMDb ID',
                'Video Quality',
                'Audio Format',
                'Language',
                'Actors',
                'Trailer URL',
                'Seen',
                'Date Seen',
                'Personal Rating',
                'Franchise',
                'Franchise Position',
                'Status',
                'Condition',
                'Purchase Date',
                'Purchase Price',
                'Barcode',
                'Notes',
            ],
            'book' => [
                'Title',
                'Author',
                'ISBN',
                'Publisher',
                'Pages',
                'Genre',
                'Release Year',
                'Series',
                'Series Position',
                'Read',
                'Date Finished',
                'Personal Rating',
                'Franchise',
                'Franchise Position',
                'Status',
                'Condition',
                'Purchase Date',
                'Purchase Price',
                'Barcode',
                'Notes',
            ],
            'game' => [
                'Title',
                'Platform',
                'Format',
                'Genre',
                'Publisher',
                'Release Year',
                'Completed',
                'Completion Date',
                'Personal Rating',
                'Franchise',
                'Franchise Position',
                'Status',
                'Condition',
                'Purchase Date',
                'Purchase Price',
                'Barcode',
                'Notes',
            ],
            'music' => [
                'Title',
                'Artist',
                'Format',
                'Genre',
                'Label',
                'Tracks',
                'Vinyl Speed',
                'Release Year',
                'Personal Rating',
                'Franchise',
                'Franchise Position',
                'Status',
                'Condition',
                'Purchase Date',
                'Purchase Price',
                'Barcode',
                'Notes',
            ],
            default => [],
        };
    }

    private function flattenArrayField($value): string
    {
        if (is_array($value)) {
            // Actors: extract names
            if (isset($value[0]['name'])) {
                return implode(', ', array_column($value, 'name'));
            }
            // Plain arrays like audio_format
            return implode(', ', $value);
        }
        return (string) ($value ?? '');
    }

    private function getFranchiseName($detail): string
    {
        if ($detail->franchise)
            return $detail->franchise->name;
        return '';
    }

    private function getSeriesName($detail): string
    {
        if ($detail->series)
            return $detail->series->name;
        return '';
    }

    private function mapRow(array $item, object $detail, string $type): array
    {
        $base = [$item['title']];

        return match ($type) {
            'movie' => [
                ...$base,
                $detail->format ?? '',
                $detail->director ?? '',
                $detail->genre ?? '',
                $detail->release_year ?? '',
                $detail->runtime_minutes ?? '',
                $detail->imdb_id ?? '',
                $detail->video_quality ?? '',
                $this->flattenArrayField($detail->audio_format),
                $detail->language ?? '',
                $this->flattenArrayField($detail->actors),
                $detail->trailer_url ?? '',
                $detail->seen ? 'Yes' : 'No',
                $detail->date_seen ?? '',
                $detail->personal_rating ?? '',
                $this->getFranchiseName($detail),
                $detail->franchise_position ?? '',
                $item['status'],
                $item['condition'],
                $item['purchase_date'] ?? '',
                $item['purchase_price'] ?? '',
                $item['barcode'] ?? '',
                $item['notes'] ?? '',
            ],
            'book' => [
                ...$base,
                $detail->author ?? '',
                $detail->isbn ?? '',
                $detail->publisher ?? '',
                $detail->page_count ?? '',
                $detail->genre ?? '',
                $detail->release_year ?? '',
                $this->getSeriesName($detail),
                $detail->series_position ?? '',
                $detail->read ? 'Yes' : 'No',
                $detail->date_finished ?? '',
                $detail->personal_rating ?? '',
                $this->getFranchiseName($detail),
                $detail->franchise_position ?? '',
                $item['status'],
                $item['condition'],
                $item['purchase_date'] ?? '',
                $item['purchase_price'] ?? '',
                $item['barcode'] ?? '',
                $item['notes'] ?? '',
            ],
            'game' => [
                ...$base,
                $detail->platform ?? '',
                $detail->format ?? '',
                $detail->genre ?? '',
                $detail->publisher ?? '',
                $detail->release_year ?? '',
                $detail->completed ? 'Yes' : 'No',
                $detail->completion_date ?? '',
                $detail->personal_rating ?? '',
                $this->getFranchiseName($detail),
                $detail->franchise_position ?? '',
                $item['status'],
                $item['condition'],
                $item['purchase_date'] ?? '',
                $item['purchase_price'] ?? '',
                $item['barcode'] ?? '',
                $item['notes'] ?? '',
            ],
            'music' => [
                ...$base,
                $detail->artist ?? '',
                $detail->format ?? '',
                $detail->genre ?? '',
                $detail->label ?? '',
                $detail->track_count ?? '',
                $detail->vinyl_speed ?? '',
                $detail->release_year ?? '',
                $detail->personal_rating ?? '',
                $this->getFranchiseName($detail),
                $detail->franchise_position ?? '',
                $item['status'],
                $item['condition'],
                $item['purchase_date'] ?? '',
                $item['purchase_price'] ?? '',
                $item['barcode'] ?? '',
                $item['notes'] ?? '',
            ],
            default => $base,
        };
    }

    public function export(Request $request, string $type)
    {
        $validTypes = ['movie', 'book', 'game', 'music', 'tv_show'];
        if (!in_array($type, $validTypes)) {
            return response()->json(['error' => 'Invalid type'], 422);
        }

        $modelClass = $this->getModelClass($type);
        $typeLabels = [
            'movie' => 'Movies',
            'book' => 'Books',
            'game' => 'Games',
            'music' => 'Music',
            'tv_show' => 'TV Shows',
        ];
        $label = $typeLabels[$type];
        $date = now()->format('Y-m-d');

        if ($type === 'tv_show') {
            return $this->exportJson($type, $modelClass, $label, $date);
        }

        return $this->exportCsv($type, $modelClass, $label, $date);
    }

    private function exportCsv(string $type, string $modelClass, string $label, string $date)
    {
        $columns = $this->getColumns($type);
        $filename = "{$label}_{$date}.csv";

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        return Response::stream(function () use ($type, $modelClass, $columns) {
            $file = fopen('php://output', 'w');
            fwrite($file, "\xEF\xBB\xBF");
            fputcsv($file, $columns);

            $detailQuery = $modelClass::whereIn('collection_item_id', []);
            if ($type === 'book') {
                $detailQuery = $modelClass::query()->with('series', 'franchise');
            } else {
                $detailQuery = $modelClass::query()->with('franchise');
            }

            CollectionItem::where('user_id', Auth::id())
                ->where('type', $type)
                ->orderBy('title', 'asc')
                ->chunk(200, function ($items) use ($type, $detailQuery, $file) {
                    $ids = $items->pluck('id');
                    $details = (clone $detailQuery)
                        ->whereIn('collection_item_id', $ids)
                        ->get()
                        ->keyBy('collection_item_id');

                    foreach ($items as $item) {
                        $detail = $details->get($item->id);
                        if (!$detail)
                            continue;

                        fputcsv($file, $this->mapRow([
                            'title' => $item->title,
                            'status' => $item->status,
                            'condition' => $item->condition,
                            'purchase_date' => $item->purchase_date?->format('Y-m-d'),
                            'purchase_price' => $item->purchase_price,
                            'barcode' => $item->barcode,
                            'notes' => $item->notes,
                        ], $detail, $type));
                    }
                });

            fclose($file);
        }, 200, $headers);
    }

    private function exportJson(string $type, string $modelClass, string $label, string $date)
    {
        $filename = "{$label}_{$date}.json";

        $items = CollectionItem::where('user_id', Auth::id())
            ->where('type', $type)
            ->orderBy('title', 'asc')
            ->get();

        $ids = $items->pluck('id');
        $details = $modelClass::whereIn('collection_item_id', $ids)
            ->with('franchise')
            ->get()
            ->keyBy('collection_item_id');

        $export = $items->map(function ($item) use ($details) {
            $detail = $details->get($item->id);
            return [
                'title' => $item->title,
                'status' => $item->status,
                'condition' => $item->condition,
                'purchase_date' => $item->purchase_date?->format('Y-m-d'),
                'purchase_price' => $item->purchase_price,
                'barcode' => $item->barcode,
                'notes' => $item->notes,
                'details' => $detail ? [
                    'format' => $detail->format,
                    'network' => $detail->network,
                    'director' => $detail->director,
                    'total_seasons' => $detail->total_seasons,
                    'total_episodes' => $detail->total_episodes,
                    'genre' => $detail->genre,
                    'release_year' => $detail->release_year,
                    'watch_status' => $detail->watch_status,
                    'current_season' => $detail->current_season,
                    'current_episode' => $detail->current_episode,
                    'personal_rating' => $detail->personal_rating,
                    'trailer_url' => $detail->trailer_url,
                    'actors' => $detail->actors,
                    'seasons' => $detail->seasons,
                    'franchise' => $detail->franchise ? $detail->franchise->name : null,
                    'franchise_position' => $detail->franchise_position,
                ] : null,
            ];
        });

        return Response::stream(function () use ($export) {
            $file = fopen('php://output', 'w');
            fwrite($file, json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            fclose($file);
        }, 200, [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function exportFullZip(Request $request, string $type)
    {
        $validTypes = ['movie', 'book', 'game', 'music', 'tv_show'];
        if (!in_array($type, $validTypes)) {
            return response()->json(['error' => 'Invalid type'], 422);
        }

        $modelClass = $this->getModelClass($type);
        $typeLabels = ['movie' => 'Movies', 'book' => 'Books', 'game' => 'Games', 'music' => 'Music', 'tv_show' => 'TV_Shows'];
        $label = $typeLabels[$type];
        $date = now()->format('Y-m-d');
        $filename = "{$label}_Full_Backup_{$date}.zip";

        $items = CollectionItem::where('user_id', Auth::id())
            ->where('type', $type)
            ->orderBy('title', 'asc')
            ->get();

        $ids = $items->pluck('id');

        $detailQuery = $modelClass::query();
        if ($type === 'book') {
            $detailQuery->with('series', 'franchise');
        } else {
            $detailQuery->with('franchise');
        }
        $details = $detailQuery->whereIn('collection_item_id', $ids)->get()->keyBy('collection_item_id');

        $exportData = $items->map(function ($item) use ($details) {
            $detail = $details->get($item->id);
            $detailArray = $detail ? $detail->toArray() : null;

            // Replace franchise_id/series_id with human-readable names
            if ($detailArray) {
                unset($detailArray['collection_item_id']);
                unset($detailArray['franchise_id']);
                unset($detailArray['series_id']);

                if ($detail->franchise) {
                    $detailArray['franchise'] = $detail->franchise->name;
                }
                if ($detail->series) {
                    $detailArray['series'] = $detail->series->name;
                }
            }

            return [
                '_old_id' => $item->id,
                'title' => $item->title,
                'status' => $item->status,
                'condition' => $item->condition,
                'purchase_date' => $item->purchase_date?->format('Y-m-d'),
                'purchase_price' => $item->purchase_price,
                'barcode' => $item->barcode,
                'notes' => $item->notes,
                'details' => $detailArray,
            ];
        })->toArray();

        $tempFile = tempnam(sys_get_temp_dir(), 'vault_export_') . '.zip';
        $zip = new \ZipArchive();

        if ($zip->open($tempFile, \ZipArchive::CREATE) !== true) {
            return response()->json(['error' => 'Failed to create zip file'], 500);
        }

        $zip->addFromString('data.json', json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $zip->addEmptyDir('covers');
        foreach ($items as $item) {
            if ($item->cover_image) {
                $path = storage_path('app/public/' . $item->cover_image);
                if (File::exists($path)) {
                    $ext = pathinfo($path, PATHINFO_EXTENSION);
                    $zip->addFile($path, 'covers/' . $item->id . '.' . $ext);
                }
            }
        }

        $zip->close();

        return Response::stream(function () use ($tempFile) {
            $stream = fopen($tempFile, 'r');
            fpassthru($stream);
            fclose($stream);
            unlink($tempFile);
        }, 200, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Content-Length' => filesize($tempFile),
        ]);
    }
}