<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class RawgController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => 'required|string|min:2|max:255',
        ]);

        $apiKey = env('RAWG_API_KEY');
        if (!$apiKey)
            return response()->json(['error' => 'RAWG_API_KEY not set'], 500);

        try {
            $response = Http::timeout(10)->withoutVerifying()->get('https://api.rawg.io/api/games', [
                'key' => $apiKey,
                'search' => $validated['query'],  // Changed from $request->query
                'page_size' => 10,
            ]);

            if (!$response->successful()) {
                return response()->json(['error' => 'RAWG returned status ' . $response->status()], 502);
            }

            $body = $response->json();
            $results = [];

            foreach ($body['results'] ?? [] as $item) {
                $results[] = [
                    'id' => $item['id'],
                    'title' => $item['name'] ?? null,
                    'year' => !empty($item['released']) ? substr($item['released'], 0, 4) : null,
                    'poster_url' => !empty($item['background_image'])
                        ? '/api/rawg/poster?url=' . urlencode($item['background_image'])
                        : null,
                ];
            }

            return response()->json(['results' => $results]);
        } catch (\Exception $e) {
            Log::error('RAWG search failed', ['message' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function details(Request $request): JsonResponse
    {
        $request->validate([
            'rawg_id' => 'required|integer',
        ]);

        $apiKey = env('RAWG_API_KEY');
        if (!$apiKey)
            return response()->json(['error' => 'RAWG_API_KEY not set'], 500);

        try {
            $details = Http::timeout(10)->withoutVerifying()->get("https://api.rawg.io/api/games/{$request->rawg_id}", [
                'key' => $apiKey,
            ])->json();

            if (!$details || !isset($details['id'])) {
                return response()->json(['error' => 'Game not found on RAWG'], 404);
            }

            // Developer
            $developer = null;
            if (is_array($details['developers'] ?? null) && count($details['developers']) > 0) {
                $developer = $details['developers'][0]['name'];
            }

            // Publisher
            $publisher = null;
            if (is_array($details['publishers'] ?? null) && count($details['publishers']) > 0) {
                $publisher = $details['publishers'][0]['name'];
            }

            // Genres
            $genre = '';
            if (is_array($details['genres'] ?? null)) {
                $genre = implode(', ', array_column($details['genres'], 'name'));
            }

            // Guess Platform (Map RAWG slugs to your DB formats)
            $platform = null;
            $platformMap = [
                'playstation5' => 'PS5',
                'ps5' => 'PS5',
                'playstation4' => 'PS4',
                'ps4' => 'PS4',
                'playstation3' => 'PS3',
                'ps3' => 'PS3',
                'ps-vita' => 'PS Vita',
                'nintendo-switch' => 'Switch',
                'switch' => 'Switch',
                'wii-u' => 'Wii U',
                'wii' => 'Wii',
                'xbox-series-x' => 'Xbox Series X',
                'xbox-series-s' => 'Xbox Series X',
                'xbox-one' => 'Xbox One',
                'pc' => 'PC',
                'steam' => 'Steam',
            ];

            if (is_array($details['platforms'] ?? null)) {
                $slugs = array_column($details['platforms'], 'slug');
                $flatSlugs = array_merge(...$slugs); // RAWG platforms is nested: [{platform: {slug: 'ps5'}}]
                foreach ($platformMap as $slug => $mapped) {
                    if (in_array($slug, $flatSlugs)) {
                        $platform = $mapped;
                        break;
                    }
                }
            }

            // Overview (strip HTML tags RAWG sometimes includes)
            $overview = $details['description_raw'] ?? strip_tags($details['description'] ?? '');

            // Download Cover
            $coverImage = $this->downloadPoster($details['background_image'], $details['id']);

            return response()->json([
                'title' => $details['name'] ?? '',
                'cover_image' => $coverImage,
                'developer' => $developer,
                'publisher' => $publisher,
                'genre' => $genre,
                'release_year' => isset($details['released']) ? (int) substr($details['released'], 0, 4) : null,
                'platform' => $platform,
                'overview' => $overview,
            ]);
        } catch (\Exception $e) {
            Log::error('RAWG details failed', ['message' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function proxyPoster(Request $request)
    {
        $request->validate(['url' => 'required|url']);
        try {
            $response = Http::timeout(10)->withoutVerifying()->get($request->url);
            if (!$response->successful() || strlen($response->body()) < 2000)
                return response('', 404);
            return response($response->body(), 200, [
                'Content-Type' => 'image/jpeg',
                'Cache-Control' => 'public, max-age=604800',
            ]);
        } catch (\Exception $e) {
            return response('', 404);
        }
    }

    private function downloadPoster(?string $imageUrl, int $id): ?string
    {
        if (empty($imageUrl))
            return null;
        try {
            $response = Http::timeout(10)->withoutVerifying()->get($imageUrl);
            if (!$response->successful() || strlen($response->body()) < 2000)
                return null;

            $path = "covers/rawg_" . $id . ".jpg";
            Storage::disk('public')->put($path, $response->body());
            return "/storage/" . $path;
        } catch (\Exception $e) {
            return null;
        }
    }
}