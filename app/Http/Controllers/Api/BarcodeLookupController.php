<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class BarcodeLookupController extends Controller
{
    public function lookup(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'required|in:movie,book,game,music,tv_show',
            'barcode' => 'required|string|max:20',
        ]);

        $type = $request->input('type');
        $barcode = $request->input('barcode');

        return match ($type) {
            'book' => $this->lookupBook($barcode),
            'music' => $this->lookupMusic($barcode),
            'movie' => $this->lookupGeneric($barcode, 'movie'),
            'game' => $this->lookupGeneric($barcode, 'game'),
            'tv_show' => $this->lookupGeneric($barcode, 'TV show'),
        };
    }

    // ═══ BOOKS: Open Library (free, no key) ═══

    private function lookupBook(string $barcode): JsonResponse
    {
        $isbn = preg_replace('/[\s\-]/', '', $barcode);

        try {
            $response = Http::timeout(15)
                ->withoutVerifying()
                ->get("https://openlibrary.org/isbn/{$isbn}.json");

            if (!$response->successful() || !$response->json()) {
                return $this->notFound($barcode, 'No book found for this ISBN.');
            }

            $data = $response->json();
            $coverPath = $this->downloadCover("https://covers.openlibrary.org/b/isbn/{$isbn}-L.jpg", "covers/book_{$isbn}.jpg");

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
                'cover_image' => $coverPath,
            ]);
        } catch (\Exception $e) {
            return $this->serviceError($barcode, $e);
        }
    }

    // ═══ MUSIC: MusicBrainz (free, no key, excellent barcode support) ═══

    private function lookupMusic(string $barcode): JsonResponse
    {
        try {
            sleep(1); // MusicBrainz rate limit: 1 req/sec
            // MusicBrainz requires a User-Agent header
            $response = Http::timeout(15)
                ->withoutVerifying()
                ->withHeaders(['User-Agent' => 'MediaVault/1.0 (media-vault.local)'])
                ->get("https://musicbrainz.org/ws/2/release/", [
                    'query' => "barcode:{$barcode}",
                    'fmt' => 'json',
                    'limit' => 1,
                ]);

            if (!$response->successful() || empty($response->json('releases'))) {
                return $this->notFound($barcode, 'No album found for this barcode in MusicBrainz.');
            }

            $release = $response->json('releases.0');
            $mbid = $release['id'];

            // Artist
            $artist = '';
            if (!empty($release['artist-credit'])) {
                $artist = $release['artist-credit'][0]['name'] ?? '';
            }

            // Label
            $label = '';
            if (!empty($release['label-info'])) {
                $label = $release['label-info'][0]['label']['name'] ?? '';
            }

            // Track count from all media
            $trackCount = 0;
            if (!empty($release['media'])) {
                foreach ($release['media'] as $medium) {
                    $trackCount += $medium['track-count'] ?? 0;
                }
            }

            // Year
            $year = null;
            if (!empty($release['date'])) {
                $year = substr($release['date'], 0, 4);
            } elseif (!empty($release['release-events'])) {
                $year = substr($release['release-events'][0]['date'] ?? '', 0, 4);
            }

            // Detect vinyl speed from format
            $vinylSpeed = null;
            if (!empty($release['media'])) {
                $format = strtolower($release['media'][0]['format'] ?? '');
                if (str_contains($format, '33'))
                    $vinylSpeed = '33';
                elseif (str_contains($format, '45'))
                    $vinylSpeed = '45';
                elseif (str_contains($format, '78'))
                    $vinylSpeed = '78';
            }

            // Detect format
            $format = 'CD';
            if (!empty($release['media'])) {
                $rawFormat = strtolower($release['media'][0]['format'] ?? '');
                if (str_contains($rawFormat, 'vinyl') || str_contains($rawFormat, 'lp'))
                    $format = 'Vinyl';
                elseif (str_contains($rawFormat, 'cassette'))
                    $format = 'Cassette';
                elseif (str_contains($rawFormat, 'digital'))
                    $format = 'Digital';
                elseif (str_contains($rawFormat, '8-track'))
                    $format = '8-Track';
            }

            // Cover art from CoverArtArchive (free, linked to MusicBrainz)
            $coverPath = $this->downloadCover(
                "https://coverartarchive.org/release/{$mbid}/front-500",
                "covers/music_{$mbid}.jpg"
            );

            return response()->json([
                'barcode' => $barcode,
                'auto_filled' => true,
                'title' => $release['title'] ?? '',
                'artist' => $artist,
                'label' => $label,
                'track_count' => $trackCount ?: null,
                'release_year' => $year ? (int) $year : null,
                'format' => $format,
                'vinyl_speed' => $vinylSpeed,
                'cover_image' => $coverPath,
            ]);
        } catch (\Exception $e) {
            return $this->serviceError($barcode, $e);
        }
    }

    // ═══ MOVIES & GAMES: Store barcode, explain limitation ═══

    private function lookupGeneric(string $barcode, string $type): JsonResponse
    {
        $label = $type === 'movie' ? 'movie/DVD/Blu-ray' : 'game';

        return response()->json([
            'barcode' => $barcode,
            'auto_filled' => false,
            'message' => "Barcode saved. There is no free API that maps {$label} barcodes to metadata — you'll need to fill in the details manually.",
        ]);
    }

    // ═══ Helpers ═══

    private function downloadCover(string $url, string $path): ?string
    {
        try {
            $response = Http::timeout(10)->withoutVerifying()->get($url);
            if ($response->successful() && strlen($response->body()) > 1000) {
                Storage::disk('public')->put($path, $response->body());
                return $path;
            }
        } catch (\Exception $e) {
            Log::warning('Cover download failed', ['url' => $url, 'error' => $e->getMessage()]);
        }
        return null;
    }

    private function notFound(string $barcode, string $message): JsonResponse
    {
        return response()->json([
            'barcode' => $barcode,
            'auto_filled' => false,
            'message' => $message,
        ], 404);
    }

    private function serviceError(string $barcode, \Exception $e): JsonResponse
    {
        Log::error('Barcode lookup failed', [
            'barcode' => $barcode,
            'error' => $e->getMessage(),
        ]);

        return response()->json([
            'barcode' => $barcode,
            'auto_filled' => false,
            'message' => 'Lookup service unavailable. Try again or fill in manually.',
            'debug' => $e->getMessage(),
        ], 503);
    }
}