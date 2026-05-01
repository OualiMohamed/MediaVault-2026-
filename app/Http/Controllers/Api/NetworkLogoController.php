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

        $show = $request->input('show');
        $network = strtolower(trim($request->input('network')));

        try {
            $response = Http::timeout(8)
                ->withoutVerifying()
                ->get("https://api.tvmaze.com/search/shows", [
                    'q' => $show,
                ]);

            if (!$response->successful() || !is_array($response->json())) {
                return response()->json(['logo' => null]);
            }

            $results = $response->json();

            // Try exact network name match first
            $match = null;
            foreach ($results as $item) {
                $itemNetwork = strtolower($item['show']['network']['name'] ?? '');
                if ($itemNetwork === $network) {
                    $match = $item;
                    break;
                }
            }

            // Fallback: partial match
            if (!$match) {
                foreach ($results as $item) {
                    $itemNetwork = strtolower($item['show']['network']['name'] ?? '');
                    if (str_contains($itemNetwork, $network) || str_contains($network, $itemNetwork)) {
                        $match = $item;
                        break;
                    }
                }
            }

            $logo = null;
            if ($match) {
                $logo = $match['show']['network']['image']['medium']
                    ?? $match['show']['network']['image']['original']
                    ?? null;
            }

            return response()->json(['logo' => $logo]);
        } catch (\Exception $e) {
            Log::warning('Network logo lookup failed', [
                'show' => $show,
                'network' => $network,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['logo' => null]);
        }
    }
}