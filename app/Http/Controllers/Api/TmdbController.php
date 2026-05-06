<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class TmdbController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'type' => 'required|in:movie,tv_show',
                'query' => 'required|string|min:2|max:255',
            ]);
        } catch (\Illuminate\Validation\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        $apiKey = env('TMDB_API_KEY');
        if (!$apiKey) {
            return response()->json(['error' => 'TMDB_API_KEY not set in .env file'], 500);
        }

        $endpoint = $validated['type'] === 'movie' ? 'search/movie' : 'search/tv';

        try {
            $response = Http::timeout(10)->withoutVerifying()->get(
                "https://api.themoviedb.org/3/{$endpoint}",
                [
                    'query' => $validated['query'],
                    'api_key' => $apiKey,
                ]
            );

            if (!$response->successful()) {
                return response()->json([
                    'error' => "TMDB returned status " . $response->status(),
                ], 502);
            }

            $body = $response->json();

            if (!is_array($body['results'] ?? null)) {
                return response()->json(['error' => 'No results from TMDB']);
            }

            $results = [];
            foreach ($body['results'] as $item) {
                $itemRow = [
                    'id' => $item['id'] ?? null,
                    'title' => $item['title'] ?? $item['name'] ?? null,
                    'year' => null,
                    'poster_url' => null,
                    'overview' => $item['overview'] ?? null,
                ];

                if (!empty($item['release_date'])) {
                    $itemRow['year'] = substr($item['release_date'], 0, 4);
                }
                if (!empty($item['first_air_date'])) {
                    $itemRow['year'] = substr($item['first_air_date'], 0, 4);
                }
                if (!empty($item['poster_path'])) {
                    $itemRow['poster_url'] = "/api/tmdb/poster?size=w185&path=" . $item['poster_path'];
                }

                $results[] = $itemRow;
            }

            return response()->json(['results' => $results]);
        } catch (\Exception $e) {
            Log::error('TMDB search failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return response()->json([
                'error' => $e->getMessage(),
                'file' => basename($e->getFile()),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    public function details(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'type' => 'required|in:movie,tv_show',
                'tmdb_id' => 'required|integer',
            ]);
        } catch (\Illuminate\Validation\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        $apiKey = env('TMDB_API_KEY');
        if (!$apiKey) {
            return response()->json(['error' => 'TMDB_API_KEY not set in .env'], 500);
        }

        $type = $validated['type'];
        $tmdbId = $validated['tmdb_id'];

        try {
            if ($type === 'movie') {
                return $this->movieDetails($tmdbId, $apiKey);
            }
            return $this->tvShowDetails($tmdbId, $apiKey);
        } catch (\Exception $e) {
            Log::error("TMDB details failed for {$type} #{$tmdbId}", [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return response()->json([
                'error' => $e->getMessage(),
                'file' => basename($e->getFile()),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    public function poster(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'url' => 'required|url|max:500',
            ]);
        } catch (\Illuminate\Validation\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        $apiKey = env('TMDB_API_KEY');

        try {
            $response = Http::timeout(10)->withoutVerifying()->get($validated['url']);

            if (!$response->successful() || strlen($response->body()) < 2000) {
                return response()->json(['url' => null], 404);
            }

            $path = 'covers/tmdb_' . uniqid() . '.jpg';
            Storage::disk('public')->put($path, $response->body());

            return response()->json(['url' => '/storage/' . $path]);
        } catch (\Exception $e) {
            return response()->json(['url' => null], 500);
        }
    }

    public function proxyPoster(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'size' => 'nullable|in:w92,w154,w185,w342,w500,w780',
                'path' => 'required|string',
            ]);
        } catch (\Illuminate\Validation\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        $apiKey = env('TMDB_API_KEY');
        if (!$apiKey) {
            return response()->json(['error' => 'TMDB_API_KEY not set in .env'], 500);
        }

        $size = $validated['size'] ?? 'w500';
        $url = "https://image.tmdb.org/t/" . $size . "/" . $validated['path'];

        try {
            $response = Http::timeout(10)->withoutVerifying()->get($url);

            if (!$response->successful() || strlen($response->body()) < 2000) {
                return response('', 404);
            }

            return response($response->body(), 200, [
                'Content-Type' => 'image/jpeg',
                'Cache-Control' => 'public, max-age=604800',
            ]);
        } catch (\Exception $e) {
            return response('', 404);
        }
    }

    private function movieDetails(int $tmdbId, string $apiKey): JsonResponse
    {
        $details = Http::timeout(10)->withoutVerifying()->get(
            "https://api.themoviedb.org/3/movie/" . $tmdbId,
            ['api_key' => $apiKey]
        )->json();

        if (!$details) {
            return response()->json(['error' => 'Movie not found on TMDB'], 404);
        }

        // Director
        $director = null;
        $credits = Http::timeout(10)->withoutVerifying()->get(
            "https://api.themoviedb.org/3/movie/" . $tmdbId . "/credits",
            ['api_key' => $apiKey]
        )->json();

        if (is_array($credits['crew'] ?? null)) {
            foreach ($credits['crew'] as $person) {
                if (($person['job'] ?? '') === 'Director' && ($person['department'] ?? '') === 'Directing') {
                    $director = $person['name'];
                    break;
                }
            }
        }

        // ADD THIS: Extract top 8 actors
        $actors = [];
        if (is_array($credits['cast'] ?? null)) {
            $actors = collect($credits['cast'])
                ->take(8)
                ->map(fn($a) => [
                    'name' => $a['name'] ?? null,
                    'character' => $a['character'] ?? null,
                    'tmdb_id' => $a['id'] ?? null,
                ])
                ->filter(fn($a) => !empty($a['name']))
                ->values()
                ->toArray();
        }

        // Trailer
        $trailer = null;
        $videos = Http::timeout(10)->withoutVerifying()->get(
            "https://api.themoviedb.org/3/movie/" . $tmdbId . "/videos",
            ['api_key' => $apiKey]
        )->json();

        if (is_array($videos['results'] ?? null)) {
            foreach ($videos['results'] as $v) {
                if (($v['type'] ?? '') === 'Trailer' && ($v['site'] ?? '') === 'YouTube' && ($v['key'] ?? null)) {
                    $trailer = "https://www.youtube.com/watch?v=" . $v['key'];
                    break;
                }
            }
        }

        // Genre names
        $genre = '';
        if (is_array($details['genres'] ?? null)) {
            $genre = implode(', ', array_column($details['genres'], 'name'));
        }

        $coverImage = $this->downloadPoster($details['poster_path'], $tmdbId);

        return response()->json([
            'title' => $details['title'] ?? '',
            'cover_image' => $coverImage,
            'director' => $director,
            'genre' => $genre,
            'release_year' => isset($details['release_date']) ? (int) substr($details['release_date'], 0, 4) : null,
            'runtime_minutes' => $details['runtime'] ?? null,
            'overview' => $details['overview'] ?? '',
            'trailer_url' => $trailer,
            'imdb_id' => $details['imdb_id'] ?? null,
            'network' => null,
            'total_seasons' => null,
            'actors' => $actors,
        ]);
    }

    private function tvShowDetails(int $tmdbId, string $apiKey): JsonResponse
    {
        $details = Http::timeout(10)->withoutVerifying()->get(
            "https://api.themoviedb.org/3/tv/" . $tmdbId,
            ['api_key' => $apiKey]
        )->json();

        if (!$details) {
            return response()->json(['error' => 'Show not found on TMDB'], 404);
        }

        // Director
        $director = null;
        $credits = Http::timeout(10)->withoutVerifying()->get(
            "https://api.themoviedb.org/3/tv/" . $tmdbId . "/credits",
            ['api_key' => $apiKey]
        )->json();

        // Map Created By to Director
        $director = null;
        if (is_array($details['created_by'] ?? null) && count($details['created_by']) > 0) {
            $director = implode(', ', array_column($details['created_by'], 'name'));
        }

        // ADD THIS: Extract top 8 actors
        $actors = [];
        if (is_array($credits['cast'] ?? null)) {
            $actors = collect($credits['cast'])
                ->take(8)
                ->map(fn($a) => [
                    'name' => $a['name'] ?? null,
                    'character' => $a['character'] ?? null,
                    'tmdb_id' => $a['id'] ?? null,
                ])
                ->filter(fn($a) => !empty($a['name']))
                ->values()
                ->toArray();
        }

        // Trailer
        $trailer = null;
        $videos = Http::timeout(10)->withoutVerifying()->get(
            "https://api.themoviedb.org/3/tv/" . $tmdbId . "/videos",
            ['api_key' => $apiKey]
        )->json();

        if (is_array($videos['results'] ?? null)) {
            foreach ($videos['results'] as $v) {
                if (($v['type'] ?? '') === 'Trailer' && ($v['site'] ?? '') === 'YouTube' && ($v['key'] ?? null)) {
                    $trailer = "https://www.youtube.com/watch?v=" . $v['key'];
                    break;
                }
            }
        }

        // Genre names
        $genre = '';
        if (is_array($details['genres'] ?? null)) {
            $genre = implode(', ', array_column($details['genres'], 'name'));
        }

        // Network
        $network = null;
        if (is_array($details['networks'] ?? null) && count($details['networks']) > 0) {
            $network = $details['networks'][0]['name'] ?? null;
        }


        $coverImage = $this->downloadPoster($details['poster_path'], $tmdbId);

        return response()->json([
            'title' => $details['name'] ?? '',
            'cover_image' => $coverImage,
            'director' => $director, // Add this
            'genre' => $genre,
            'release_year' => isset($details['first_air_date']) ? (int) substr($details['first_air_date'], 0, 4) : null,
            'runtime_minutes' => $details['episode_runtime'] ?? null,
            'overview' => $details['overview'] ?? '',
            'trailer_url' => $trailer,
            'imdb_id' => $details['imdb_id'] ?? null,
            'network' => $network,
            'total_seasons' => $details['number_of_seasons'] ?? null,
            'actors' => $actors,
        ]);
    }

    private function downloadPoster(?string $posterPath, int $id): ?string
    {
        if (empty($posterPath)) {
            return null;
        }

        try {
            $response = Http::timeout(10)->withoutVerifying()->get(
                "https://image.tmdb.org/t/p/w500" . $posterPath
            );

            if (!$response->successful() || strlen($response->body()) < 2000) {
                return null;
            }

            $path = "covers/tmdb_" . $id . ".jpg";
            Storage::disk('public')->put($path, $response->body());

            return "/storage/" . $path;
        } catch (\Exception $e) {
            return null;
        }
    }
}
