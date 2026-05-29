<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CollectionItem;
use App\Models\Movie;
use App\Models\Book;
use App\Models\Game;
use App\Models\Music;
use App\Models\TvShow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class CoverFetchController extends Controller
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

    public function missing(string $type): JsonResponse
    {
        $validTypes = ['movie', 'book', 'game', 'music', 'tv_show'];
        if (!in_array($type, $validTypes)) {
            return response()->json(['error' => 'Invalid type'], 422);
        }

        $base = CollectionItem::where('user_id', Auth::id())
            ->where('type', $type)
            ->whereNull('cover_image');

        $totalMissing = $base->count();

        $nextBatch = $base->orderBy('id')
            ->limit(10)
            ->pluck('id')
            ->toArray();

        return response()->json([
            'total_missing' => $totalMissing,
            'next_batch' => $nextBatch,
        ]);
    }

    public function fetch(Request $request, string $type): JsonResponse
    {
        $validTypes = ['movie', 'book', 'game', 'music', 'tv_show'];
        if (!in_array($type, $validTypes)) {
            return response()->json(['error' => 'Invalid type'], 422);
        }

        $request->validate([
            'item_ids' => 'required|array|max:10',
            'item_ids.*' => 'integer',
        ]);

        $itemIds = $request->item_ids;
        $modelClass = $this->getModelClass($type);

        $items = CollectionItem::where('user_id', Auth::id())
            ->where('type', $type)
            ->whereIn('id', $itemIds)
            ->get();

        $details = $modelClass::whereIn('collection_item_id', $items->pluck('id'))
            ->get()
            ->keyBy('collection_item_id');

        $fetched = 0;
        $failed = 0;

        foreach ($items as $item) {
            $detail = $details->get($item->id);
            if (!$detail) {
                \Log::warning("CoverFetch: No detail for item #{$item->id}");
                $failed++;
                continue;
            }

            $imageUrl = null;

            try {
                if ($type === 'movie' || $type === 'tv_show') {
                    $imageUrl = $this->fetchFromTmdb($detail, $item->title);
                    if (!$imageUrl)
                        \Log::warning("CoverFetch: TMDB returned no image for #{$item->id} ({$item->title}) — imdb_id: " . ($detail->imdb_id ?? 'NULL'));
                } elseif ($type === 'book') {
                    $imageUrl = $this->fetchFromGoogleBooks($detail, $item->title);
                    if (!$imageUrl)
                        \Log::warning("CoverFetch: Google Books returned no image for #{$item->id} ({$item->title}) — isbn: " . ($detail->isbn ?? 'NULL'));
                } elseif ($type === 'game') {
                    $imageUrl = $this->fetchFromRawg($detail, $item->title);
                    if (!$imageUrl)
                        \Log::warning("CoverFetch: RAWG returned no image for #{$item->id} ({$item->title})");
                }
            } catch (\Exception $e) {
                \Log::error("CoverFetch: Exception for #{$item->id} ({$item->title}): " . $e->getMessage());
            }

            if ($imageUrl) {
                $coverPath = $this->downloadCover($imageUrl, $item->id);
                if ($coverPath) {
                    CollectionItem::where('id', $item->id)->update(['cover_image' => $coverPath]);
                    $fetched++;
                    continue;
                } else {
                    \Log::warning("CoverFetch: Download failed for #{$item->id}");
                }
            }

            $failed++;
        }

        $remaining = CollectionItem::where('user_id', Auth::id())
            ->where('type', $type)
            ->whereNull('cover_image')
            ->count();

        return response()->json([
            'fetched' => $fetched,
            'failed' => $failed,
            'remaining' => $remaining,
        ]);
    }

    private function fetchFromTmdb($detail, string $title = ''): ?string
    {
        $apiKey = env('TMDB_API_KEY');
        if (!$apiKey)
            throw new \Exception("No API key — length: " . strlen($apiKey));

        $query = !empty(trim($title)) ? $title : ($detail->title ?? '');
        if (empty(trim($query)))
            throw new \Exception("No title");

        $type = $detail instanceof TvShow ? 'tv' : 'movie';
        $endpoint = $type === 'movie' ? 'search/movie' : 'search/tv';

        // Exact same pattern as TmdbController search
        $response = Http::timeout(10)->withoutVerifying()->get(
            "https://api.themoviedb.org/3/{$endpoint}",
            [
                'query' => $query,
                'api_key' => $apiKey,
            ]
        );

        if (!$response->successful())
            throw new \Exception("HTTP {$response->status()} — body: " . substr($response->body(), 0, 500));

        $data = $response->json();
        $results = $data['results'] ?? [];

        if (empty($results))
            throw new \Exception("0 results — keys: " . implode(', ', array_keys($data)));

        $match = null;
        if (!empty($detail->imdb_id)) {
            foreach ($results as $r) {
                if (($r['imdb_id'] ?? '') === $detail->imdb_id) {
                    $match = $r;
                    break;
                }
            }
        }
        if (!$match)
            $match = $results[0];

        $posterPath = $match['poster_path'] ?? null;
        if (!$posterPath)
            throw new \Exception("No poster on '" . ($match['title'] ?? '?') . "'");

        return "https://image.tmdb.org/t/p/w500{$posterPath}";
    }

    private function fetchFromGoogleBooks($detail, string $title = ''): ?string
    {
        $key = env('GOOGLE_BOOKS_API_KEY');
        if (!$key)
            throw new \Exception("No Google Books key in .env");

        $isbn = $detail->isbn ?: null;
        $query = '';
        if (!empty($isbn)) {
            $query = 'isbn:' . $isbn;
        } else {
            $author = $detail->author ?? '';
            $t = !empty(trim($title)) ? $title : ($detail->title ?? '');
            $query = trim($author . ' ' . $t);
        }
        if (empty(trim($query)))
            throw new \Exception("No ISBN or title+author for item #{$detail->collection_item_id}");

        $response = Http::timeout(10)->withoutVerifying()->get('https://www.googleapis.com/books/v1/volumes', [
            'query' => ['q' => $query, 'maxResults' => 1, 'key' => $key],
        ]);

        if (!$response->successful())
            throw new \Exception("Google Books HTTP {$response->status()}");

        $data = $response->json();
        $items = $data['items'] ?? [];
        if (empty($items))
            throw new \Exception("No results for '{$query}'");

        $links = $items[0]['volumeInfo']['imageLinks'] ?? [];
        $url = $links['large'] ?? $links['medium'] ?? $links['thumbnail'] ?? null;
        if (!$url)
            throw new \Exception("No imageLinks on '" . ($items[0]['volumeInfo']['title'] ?? '?') . "'");

        return str_replace(['zoom=1', 'zoom=2'], 'zoom=3', $url);
    }

    private function fetchFromRawg($detail, string $title = ''): ?string
    {
        $key = env('RAWG_API_KEY');
        if (!$key)
            throw new \Exception("No RAWG key in .env");

        $query = !empty(trim($title)) ? $title : ($detail->title ?? '');
        if (empty(trim($query)))
            throw new \Exception("No title for item #{$detail->collection_item_id}");

        $params = ['search' => $query, 'page_size' => 1, 'key' => $key];
        if (!empty($detail->platform)) {
            $params['platforms'] = $detail->platform;
        }

        $response = Http::timeout(10)->withoutVerifying()->get('https://api.rawg.io/api/games', ['query' => $params]);

        if (!$response->successful())
            throw new \Exception("RAWG HTTP {$response->status()}");

        $results = $response->json('results') ?? [];
        return $results[0]['background_image'] ?? null;
    }

    private function downloadCover(string $url, int $itemId): ?string
    {
        $response = Http::timeout(10)->withoutVerifying()->get($url);

        if (!$response->successful()) {
            \Log::error("CoverFetch: Download failed — status " . $response->status());
            return null;
        }
        if (!str_starts_with($response->header('Content-Type'), 'image/')) {
            \Log::error("CoverFetch: Not an image — Content-Type: " . $response->header('Content-Type'));
            return null;
        }

        $ext = match (true) {
            str_contains($response->header('Content-Type'), 'png') => 'png',
            str_contains($response->header('Content-Type'), 'webp') => 'webp',
            default => 'jpg',
        };

        $path = "covers/auto_{$itemId}_" . time() . ".{$ext}";
        Storage::disk('public')->put($path, $response->body());
        return $path;
    }


    private function getFailReason(string $type, $detail): string
    {
        if ($type === 'movie' || $type === 'tv_show') {
            if (empty($detail->imdb_id))
                return 'No IMDb ID — cannot lookup on TMDB';
            if (!config('services.tmdb.key'))
                return 'TMDB API key not configured';
            return 'Has IMDb ID — TMDB lookup should work';
        }
        if ($type === 'book') {
            if (empty($detail->isbn) && (empty($detail->title) || empty($detail->author)))
                return 'No ISBN and no title+author — cannot lookup';
            if (!config('services.google_books.key'))
                return 'Google Books API key not configured';
            return 'Has lookup data — should work';
        }
        if ($type === 'game') {
            if (empty($detail->title))
                return 'No title — cannot lookup';
            if (!config('services.rawg.key'))
                return 'RAWG API key not configured';
            return 'Has title — RAWG lookup should work';
        }
        return 'Type not supported';
    }
}