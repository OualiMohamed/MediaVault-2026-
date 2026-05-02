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
        $request->validate([
            'type' => 'required|in:movie,tv_show',
            'query' => 'required|string|min:2|max:255',
        ]);

        $apiKey = env('TMDB_API_KEY');
        if (!$apiKey) {
            return response()->json(['error' => 'TMDB API key not configured'], 500);
        }

        $endpoint = $request->input('type') === 'movie' ? 'search/movie' : 'search/tv';

        try {
            $response = Http::timeout(8)->withoutVerifying()->get("https://api.themoviedb.org/3/{$endpoint}", [
                'query' => $request->input('query'),
                'api_key' => $apiKey,
            ]);

            if (!$response->successful()) {
                return response()->json(['error' => 'TMDB API error', 'status' => $response->status()], 502);
            }

            $results = $response->json('results');
            if (!is_array($results)) {
                return response()->json(['results' => []]);
            }

            $mapped = array_map(function ($item) use ($request) {
                $type = $request->input('type');
                return [
                    'id' => $item['id'],
                    'title' => $item['title'] ?? $item['name'] ?? '',
                    'year' => ($item['release_date'] ?? $item['first_air_date'] ?? '') ? substr($item['release_date'] ?? $item['first_air_date'], 0, 4) : null,
                    'poster_url' => ($item['poster_path'] ?? null) ? "https://image.tmdb.org/t/p/w185{$item['poster_path']}" : null,
                    'overview' => $item['overview'] ?? '',
                    'genre_ids' => array_column($item['genre_ids'] ?? [], 0),
                ];
            }, $results);

            return response()->json(['results' => array_values($mapped)]);
        } catch (\Exception $e) {
            Log::warning('TMDB search failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Lookup failed'], 502);
        }
    }

    public function details(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'required|in:movie,tv_show',
            'tmdb_id' => 'required|integer',
        ]);

        $apiKey = env('TMDB_API_KEY');
        if (!$apiKey) {
            return response()->json(['error' => 'TMDB API key not configured'], 500);
        }

        $type = $request->input('type');
        $tmdbId = $request->input('tmdb_id');

        try {
            if ($type === 'movie') {
                return $this->movieDetails($tmdbId, $apiKey);
            }
            return $this->tvShowDetails($tmdbId, $apiKey);
        } catch (\Exception $e) {
            Log::warning("TMDB details failed for {$type} #{$tmdb_id}", ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to fetch details'], 502);
        }
    }

    private function movieDetails(int $tmdbId, string $apiKey): JsonResponse
    {
        // Fetch main details
        $details = Http::timeout(8)->withoutVerifying()->get("https://api.themoviedb.org/3/movie/{$tmdbId}", [
            'api_key' => $apiKey,
        ])->json();

        // Fetch credits for director
        $credits = Http::timeout(8)->withoutVerifying()->get("https://api.themoviedb.org/3/movie/{$tmdbId}/credits", [
            'api_key' => $apiKey,
        ])->json();

        // Fetch videos for trailer
        $videos = Http::timeout(8)->withoutVerifying()->get("https://api.themoviedb.org/3/movie/{$tmdbId}/videos", [
            'api_key' => $apiKey,
        ])->json();

        // Director
        $director = null;
        if (is_array($credits['crew'] ?? null)) {
            foreach ($credits['crew'] as $person) {
                if (($person['job'] ?? '') === 'Director' && ($person['department'] ?? '') === 'Directing') {
                    $director = $person['name'];
                    break;
                }
            }
        }

        // Trailer
        $trailer = null;
        if (is_array($videos['results'] ?? null)) {
            foreach ($videos['results'] as $v) {
                if (($v['type'] ?? '') === 'Trailer' && ($v['site'] ?? '') === 'YouTube' && ($v['key'] ?? null)) {
                    $trailer = "https://www.youtube.com/watch?v={$v['key']}";
                    break;
                }
            }
        }

        // Genre names
        $genre = '';
        if (is_array($details['genres'] ?? null)) {
            $genre = implode(', ', array_column($details['genres'], 'name'));
        }

        // Download poster
        $coverImage = null;
        if (!empty($details['poster_path'])) {
            $posterUrl = "https://image.tmdb.org/t/p/w500{$details['poster_path']}";
            $posterResponse = Http::timeout(10)->withoutVerifying()->get($posterUrl);
            if ($posterResponse->successful() && strlen($posterResponse->body()) > 2000) {
                $path = "covers/tmdb_{$tmdbId}.jpg";
                Storage::disk('public')->put($path, $posterResponse->body());
                $coverImage = $path;
            }
        }

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
        ]);
    }

    private function tvShowDetails(int $tmdbId, string $apiKey): JsonResponse
    {
        $details = Http::timeout(8)->withoutVerifying()->get("https://api.themoviedb.org/3/tv/{$tmdbId}", [
            'api_key' => $apiKey,
        ])->json();

        // Videos for trailer
        $videos = Http::timeout(8)->withoutVerifying()->get("https://api.themoviedb.org/3/tv/{$tmdbId}/videos", [
            'api_key' => $apiKey,
        ])->json();

        // Trailer
        $trailer = null;
        if (is_array($videos['results'] ?? null)) {
            foreach ($videos['results'] as $v) {
                if (($v['type'] ?? '') === 'Trailer' && ($v['site'] ?? '') === 'YouTube' && ($v['key'] ?? null)) {
                    $trailer = "https://www.youtube.com/watch?v={$v['key']}";
                    break;
                }
            }
        }

        // Genre names
        $genre = '';
        if (is_array($details['genres'] ?? null)) {
            $genre = implode(', ', array_column($details['genres'], 'name'));
        }

        // Network name
        $network = null;
        if (is_array($details['networks'] ?? null) && count($details['networks']) > 0) {
            $network = $details['networks'][0]['name'] ?? null;
        }

        // Download poster
        $coverImage = null;
        if (!empty($details['poster_path'])) {
            $posterUrl = "https://image.tmdb.org/t/p/w500{$details['poster_path']}";
            $posterResponse = Http::timeout(10)->withoutVerifying()->get($posterUrl);
            if ($posterResponse->successful() && strlen($posterResponse->body()) > 2000) {
                $path = "covers/tmdb_{$tmdbId}.jpg";
                Storage::disk('public')->put($path, $posterResponse->body());
                $coverImage = $path;
            }
        }

        return response()->json([
            'title' => $details['name'] ?? '',
            'cover_image' => $coverImage,
            'director' => null,
            'genre' => $genre,
            'release_year' => isset($details['first_air_date']) ? (int) substr($details['first_air_date'], 0, 4) : null,
            'runtime_minutes' => $details['episode_runtime'] ?? null,
            'overview' => $details['overview'] ?? '',
            'trailer_url' => $trailer,
            'imdb_id' => $details['imdb_id'] ?? null,
            'network' => $network,
            'total_seasons' => $details['number_of_seasons'] ?? null,
        ]);
    }
}