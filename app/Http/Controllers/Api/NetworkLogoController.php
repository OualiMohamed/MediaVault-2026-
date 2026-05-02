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
        $apiKey = env('TMDB_API_KEY');

        if (!$apiKey) {
            return response()->json(['logo' => null, 'error' => 'TMDB API key not configured']);
        }

        try {
            // Strategy 1: Search for the show by name, extract network logo
            $search = Http::timeout(8)->withoutVerifying()->get('https://api.themoviedb.org/3/search/tv', [
                'query' => $show,
                'api_key' => $apiKey,
                'include_image_language' => 'en,null',
            ]);

            if ($search->successful() && ($data = $search->json('results')) && is_array($data) && count($data) > 0) {
                $showData = $data[0];

                // Get full show details to access networks
                $details = Http::timeout(8)->withoutVerifying()->get("https://api.themoviedb.org/3/tv/{$showData['id']}", [
                    'api_key' => $apiKey,
                ]);

                if ($details->successful()) {
                    $detailsData = $details->json();
                    $networks = $detailsData['networks'] ?? [];

                    // Match network name
                    $match = null;
                    foreach ($networks as $net) {
                        if (
                            strtolower($net['name']) === strtolower($network)
                            || str_contains(strtolower($net['name']), strtolower($network))
                            || str_contains(strtolower($network), strtolower($net['name']))
                        ) {
                            $match = $net;
                            break;
                        }
                    }

                    if ($match && ($match['logo_path'] ?? null)) {
                        $logo = "https://image.tmdb.org/t/p/h60/flv5o4AIo0m7MfSDNR7T3vxHsXuPTKkp8wZ3RCgWxw.png{$match['logo_path']}";
                        return response()->json(['logo' => $logo]);
                    }
                }
            }

            // Strategy 2: Fallback — search by network name to get logo from any show
            $networkSearch = Http::timeout(8)->withoutVerifying()->get('https://api.themoviedb.org/3/search/company', [
                'query' => $network,
                'api_key' => $apiKey,
            ]);

            if ($networkSearch->successful() && ($results = $networkSearch->json('results')) && is_array($results) && count($results) > 0) {
                $company = $results[0];
                if ($company['logo_path'] ?? null) {
                    $logo = "https://image.tmdb.org/t/p/h60/flv5o4AIo0m7MfSDNR7T3vxHsXuPTKkp8wZ3RCgWxw.png{$company['logo_path']}";
                    return response()->json(['logo' => $logo]);
                }
            }

            return response()->json(['logo' => null]);
        } catch (\Exception $e) {
            Log::warning('TMDB logo lookup failed', ['error' => $e->getMessage()]);
            return response()->json(['logo' => null]);
        }
    }
}