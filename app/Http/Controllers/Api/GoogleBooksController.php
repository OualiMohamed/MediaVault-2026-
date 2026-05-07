<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GoogleBooksController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $request->validate(['query' => 'required|string|min:2|max:255']);

        $apiKey = env('GOOGLE_BOOKS_API_KEY');

        try {
            $params = [
                'q' => 'intitle:' . $request->query,
                'maxResults' => 10,
                'printType' => 'books',
            ];
            if ($apiKey)
                $params['key'] = $apiKey;

            $response = Http::timeout(10)->withoutVerifying()->get('https://www.googleapis.com/books/v1/volumes', $params);

            if (!$response->successful()) {
                return response()->json(['error' => 'Google Books API error'], 502);
            }

            $body = $response->json();
            $results = [];

            foreach ($body['items'] ?? [] as $item) {
                $info = $item['volumeInfo'] ?? [];
                $thumbnail = $info['imageLinks']['thumbnail'] ?? $info['imageLinks']['smallThumbnail'] ?? null;

                $results[] = [
                    'id' => $item['id'],
                    'title' => $info['title'] ?? 'Unknown Title',
                    'authors' => is_array($info['authors'] ?? null) ? implode(', ', $info['authors']) : '',
                    'year' => isset($info['publishedDate']) ? substr($info['publishedDate'], 0, 4) : null,
                    'poster_url' => $thumbnail ? '/api/google-books/poster?url=' . urlencode($thumbnail) : null,
                ];
            }

            return response()->json(['results' => $results]);
        } catch (\Exception $e) {
            Log::error('Google Books search failed', ['message' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function details(Request $request): JsonResponse
    {
        $request->validate(['google_id' => 'required|string']);

        $apiKey = env('GOOGLE_BOOKS_API_KEY');

        try {
            $params = [];
            if ($apiKey)
                $params['key'] = $apiKey;

            $response = Http::timeout(10)->withoutVerifying()->get("https://www.googleapis.com/books/v1/volumes/{$request->google_id}", $params);

            $details = $response->json();
            $info = $details['volumeInfo'] ?? [];

            if (!$info) {
                return response()->json(['error' => 'Book not found'], 404);
            }

            // Extract ISBN (prefer ISBN-13, fallback to ISBN-10)
            $isbn = null;
            if (is_array($info['industryIdentifiers'] ?? null)) {
                foreach ($info['industryIdentifiers'] as $id) {
                    if (($id['type'] ?? '') === 'ISBN_13') {
                        $isbn = $id['identifier'];
                        break;
                    }
                    if (($id['type'] ?? '') === 'ISBN_10' && !$isbn) {
                        $isbn = $id['identifier'];
                    }
                }
            }

            // Genre (Google calls them categories)
            $genre = '';
            if (is_array($info['categories'] ?? null)) {
                $genre = implode(', ', $info['categories']);
            }

            // Clean description (Google sometimes includes HTML)
            $overview = strip_tags($info['description'] ?? '');

            // Download Cover
            $coverImage = $this->downloadPoster($info['imageLinks']['thumbnail'] ?? $info['imageLinks']['smallThumbnail'] ?? null, $request->google_id);

            return response()->json([
                'title' => $info['title'] ?? '',
                'author' => is_array($info['authors'] ?? null) ? implode(', ', $info['authors']) : '',
                'isbn' => $isbn,
                'publisher' => $info['publisher'] ?? null,
                'page_count' => $info['pageCount'] ?? null,
                'release_year' => isset($info['publishedDate']) ? (int) substr($info['publishedDate'], 0, 4) : null,
                'genre' => $genre,
                'overview' => $overview,
                'cover_image' => $coverImage,
            ]);
        } catch (\Exception $e) {
            Log::error('Google Books details failed', ['message' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function proxyPoster(Request $request)
    {
        $request->validate(['url' => 'required|url']);

        // Force HTTPS as Google often returns HTTP
        $url = str_replace('http://', 'https://', $request->url);

        try {
            $response = Http::timeout(10)->withoutVerifying()->get($url);
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

    private function downloadPoster(?string $imageUrl, string $id): ?string
    {
        if (empty($imageUrl))
            return null;

        // Force HTTPS
        $imageUrl = str_replace('http://', 'https://', $imageUrl);

        try {
            // Append zoom=1 to get a slightly larger cover if possible
            $url = $imageUrl . (parse_url($imageUrl, PHP_URL_QUERY) ? '&' : '?') . 'zoom=1';

            $response = Http::timeout(10)->withoutVerifying()->get($url);
            if (!$response->successful() || strlen($response->body()) < 2000)
                return null;

            $path = "covers/gbooks_" . $id . ".jpg";
            Storage::disk('public')->put($path, $response->body());
            return "/storage/" . $path;
        } catch (\Exception $e) {
            return null;
        }
    }
}