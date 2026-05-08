<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DiscogsController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate(['query' => 'required|string|min:2|max:255']);

        $token = env('DISCOGS_TOKEN');
        if (!$token)
            return response()->json(['error' => 'DISCOGS_TOKEN not set'], 500);

        try {
            $response = Http::timeout(10)->withoutVerifying()->get('https://api.discogs.com/database/search', [
                'q' => $validated['query'],
                'type' => 'release',
                'per_page' => 10,
                'token' => $token,
            ]);

            if (!$response->successful()) {
                return response()->json(['error' => 'Discogs API error'], 502);
            }

            $body = $response->json();
            $results = [];

            foreach ($body['results'] ?? [] as $item) {
                // Discogs returns title as "Artist - Title"
                $titleParts = explode(' - ', $item['title'] ?? '', 2);
                $artist = trim($titleParts[0] ?? '');
                $title = trim($titleParts[1] ?? $item['title'] ?? 'Unknown');

                // Format array to string
                $format = is_array($item['format'] ?? null) ? implode(', ', $item['format']) : ($item['format'] ?? '');

                $results[] = [
                    'id' => $item['id'],
                    'title' => $title,
                    'artist' => $artist,
                    'year' => $item['year'] ?? null,
                    'format' => $format,
                    'poster_url' => !empty($item['thumb']) ? '/api/discogs/poster?url=' . urlencode($item['thumb']) : null,
                ];
            }

            return response()->json(['results' => $results]);
        } catch (\Exception $e) {
            Log::error('Discogs search failed', ['message' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function details(Request $request): JsonResponse
    {
        $validated = $request->validate(['discogs_id' => 'required|integer']);

        $token = env('DISCOGS_TOKEN');
        if (!$token)
            return response()->json(['error' => 'DISCOGS_TOKEN not set'], 500);

        try {
            $response = Http::timeout(10)->withoutVerifying()->get("https://api.discogs.com/releases/{$validated['discogs_id']}", [
                'token' => $token,
            ]);

            $details = $response->json();

            if (!isset($details['id'])) {
                return response()->json(['error' => 'Release not found'], 404);
            }

            // Artist
            $artist = null;
            if (is_array($details['artists'] ?? null) && count($details['artists']) > 0) {
                $artist = $details['artists'][0]['name'];
            }

            // Label
            $label = null;
            if (is_array($details['labels'] ?? null) && count($details['labels']) > 0) {
                $label = $details['labels'][0]['name'];
            }

            // Track Count
            $trackCount = is_array($details['tracklist'] ?? null) ? count($details['tracklist']) : null;

            // Genre & Style (Discogs separates these, we combine them)
            $genreParts = [];
            if (is_array($details['genres'] ?? null))
                $genreParts = array_merge($genreParts, $details['genres']);
            if (is_array($details['styles'] ?? null))
                $genreParts = array_merge($genreParts, $details['styles']);
            $genre = implode(', ', array_unique($genreParts));

            // Format Mapping (Discogs "Vinyl" -> "Vinyl", "File" -> "Digital")
            $format = null;
            $vinylSpeed = null;

            if (is_array($details['formats'] ?? null) && count($details['formats']) > 0) {
                $mainFormat = $details['formats'][0]['name'] ?? '';

                $formatMap = [
                    'Vinyl' => 'Vinyl',
                    'CD' => 'CD',
                    'Cassette' => 'Cassette',
                    'File' => 'Digital',
                    'DVD' => 'DVD',
                    '8-Track Cartridge' => '8-Track'
                ];
                $format = $formatMap[$mainFormat] ?? $mainFormat;

                // Extract Vinyl Speed (e.g., "33 ⅓ RPM" -> "33", "45 RPM" -> "45")
                if ($format === 'Vinyl' && is_array($details['formats'][0]['descriptions'] ?? null)) {
                    foreach ($details['formats'][0]['descriptions'] as $desc) {
                        if (preg_match('/^(\d+)/', $desc, $matches)) {
                            $vinylSpeed = $matches[1];
                            break;
                        }
                    }
                }
            }

            // Download Cover
            $coverImage = null;
            if (is_array($details['images'] ?? null) && count($details['images']) > 0) {
                $coverImage = $this->downloadPoster($details['images'][0]['uri'] ?? null, $validated['discogs_id']);
            }

            return response()->json([
                'title' => $details['title'] ?? '',
                'artist' => $artist,
                'label' => $label,
                'track_count' => $trackCount,
                'release_year' => $details['year'] ?? null,
                'genre' => $genre,
                'format' => $format,
                'vinyl_speed' => $vinylSpeed,
                'cover_image' => $coverImage,
            ]);
        } catch (\Exception $e) {
            Log::error('Discogs details failed', ['message' => $e->getMessage()]);
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

            $path = "covers/discogs_" . $id . ".jpg";
            Storage::disk('public')->put($path, $response->body());
            return "/storage/" . $path;
        } catch (\Exception $e) {
            return null;
        }
    }
}