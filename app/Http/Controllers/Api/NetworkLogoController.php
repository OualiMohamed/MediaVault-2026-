<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NetworkLogoController extends Controller
{
    public function lookup(Request $request): JsonResponse
    {
        $request->validate([
            'show' => 'required|string|max:255',
            'network' => 'required|string|max:255',
        ]);

        $show = trim($request->input('show'));
        $network = trim($request->input('network'));

        try {
            // Strategy 1: Search by show title, match network
            $logo = $this->findLogo($show, $network);

            // Strategy 2: Fallback — search by just the network name
            if (!$logo) {
                $logo = $this->findLogo($network, $network);
            }

            return response()->json(['logo' => $logo]);
        } catch (\Exception $e) {
            Log::warning('Network logo lookup failed', ['error' => $e->getMessage()]);
            return response()->json(['logo' => null]);
        }
    }

    private function findLogo(string $searchQuery, string $targetNetwork): ?string
    {
        $results = $this->searchTVMaze($searchQuery);
        if (empty($results))
            return null;

        $variations = $this->buildVariations($targetNetwork);

        foreach ($results as $item) {
            $itemNetwork = strtolower($item['show']['network']['name'] ?? '');
            foreach ($variations as $variation) {
                if ($itemNetwork === $variation || $itemNetwork === str_replace(' ', '', $variation)) {
                    return $item['show']['network']['image']['medium']
                        ?? $item['show']['network']['image']['original']
                        ?? null;
                }
            }
        }

        // Partial match fallback
        foreach ($results as $item) {
            $itemNetwork = strtolower($item['show']['network']['name'] ?? '');
            foreach ($variations as $variation) {
                if (str_contains($itemNetwork, $variation) || str_contains($variation, $itemNetwork)) {
                    return $item['show']['network']['image']['medium']
                        ?? $item['show']['network']['image']['original']
                        ?? null;
                }
            }
        }

        return null;
    }

    private function searchTVMaze(string $query): array
    {
        $response = Http::timeout(8)
            ->withoutVerifying()
            ->get('https://api.tvmaze.com/search/shows', ['q' => $query]);

        if (!$response->successful())
            return [];

        $data = $response->json();
        return is_array($data) ? $data : [];
    }

    private function buildVariations(string $network): array
    {
        $variations = [
            strtolower($network),
            str_replace(' ', '', strtolower($network)),
        ];

        // "HBO Max" → also try "Max"
        $shortWords = ['max', 'plus', 'originals', 'jr', 'sr'];
        $words = explode(' ', strtolower($network));
        if (count($words) > 1 && in_array(end($words), $shortWords)) {
            $variations[] = implode(' ', array_slice($words, 0, -1));
        }

        // Known expansions
        $expansions = [
            'hbo' => ['hbo max', 'hbo go'],
            'disney' => ['disney plus', 'disney+'],
            'nickelodeon' => ['nick'],
            'cartoon' => ['cartoon network'],
        ];

        foreach ($expansions as $key => $extras) {
            if (strtolower($network) === $key) {
                foreach ($extras as $extra) {
                    $variations[] = $extra;
                }
            }
        }

        return array_unique($variations);
    }
}