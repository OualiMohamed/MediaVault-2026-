<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class BarcodeLookupController extends Controller
{
    public function lookup(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'required|in:movie,book,game,music',
            'barcode' => 'required|string|max:20',
        ]);

        $type = $request->input('type');
        $barcode = $request->input('barcode');

        if ($type === 'book') {
            return $this->lookupBook($barcode);
        }

        // Movies, games, music — store barcode, manual fill
        return response()->json([
            'barcode' => $barcode,
            'auto_filled' => false,
            'message' => 'Barcode scanned successfully. Fill in the details manually.',
        ]);
    }

    private function lookupBook(string $barcode): JsonResponse
    {
        $isbn = preg_replace('/[\s\-]/', '', $barcode);

        try {
            $response = Http::timeout(8)->get("https://openlibrary.org/isbn/{$isbn}.json");

            if (!$response->successful() || !$response->json()) {
                return response()->json([
                    'barcode' => $barcode,
                    'auto_filled' => false,
                    'message' => 'No book found for this ISBN.',
                ], 404);
            }

            $data = $response->json();

            // Download cover image from Open Library
            $coverPath = $this->downloadCover($isbn);

            return response()->json([
                'barcode' => $barcode,
                'auto_filled' => true,
                'title' => $data['title'] ?? '',
                'author' => $data['authors'][0]['name'] ?? '',
                'publisher' => $data['publishers'][0] ?? '',
                'page_count' => $data['number_of_pages'] ?? null,
                'release_year' => isset($data['publish_date']) ? substr($data['publish_date'], 0, 4) : null,
                'genre' => $data['subjects'][0] ?? '',
                'notes' => $data['subtitle'] ?? '',
                'cover_image' => $coverPath, // local storage path or null
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'barcode' => $barcode,
                'auto_filled' => false,
                'message' => 'Lookup service unavailable. Please try again.',
            ], 503);
        }
    }

    private function downloadCover(string $isbn): ?string
    {
        try {
            $url = "https://covers.openlibrary.org/b/isbn/{$isbn}-L.jpg";
            $response = Http::timeout(5)->get($url);

            if ($response->successful() && strlen($response->body()) > 1000) {
                $path = "covers/book_{$isbn}.jpg";
                Storage::disk('public')->put($path, $response->body());
                return $path;
            }
        } catch (\Exception $e) {
            // Silently fail — user can upload manually
        }
        return null;
    }
}