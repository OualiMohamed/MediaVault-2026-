<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Franchise;
use App\Models\CollectionItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class FranchiseController extends Controller
{
    public function index(): JsonResponse
    {
        $franchises = Franchise::where('user_id', Auth::id())
            ->withCount(['movies', 'books', 'games', 'tvShows', 'music'])
            ->orderBy('name')
            ->get()
            ->map(function ($f) {
                $total = $f->movies_count + $f->books_count + $f->games_count + $f->tv_shows_count + $f->music_count;
                return [
                    'id' => $f->id,
                    'name' => $f->name,
                    'cover_image' => $f->cover_image,
                    'total_items' => $total,
                    'breakdown' => [
                        'movies' => $f->movies_count,
                        'books' => $f->books_count,
                        'games' => $f->games_count,
                        'tv_shows' => $f->tv_shows_count,
                        'music' => $f->music_count,
                    ],
                ];
            });

        return response()->json($franchises);
    }

    public function show(int $id): JsonResponse
    {
        $franchise = Franchise::where('user_id', Auth::id())
            ->findOrFail($id);

        $items = $franchise->allItems();

        return response()->json([
            'id' => $franchise->id,
            'name' => $franchise->name,
            'cover_image' => $franchise->cover_image,
            'items' => $items,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'cover_image' => 'nullable|image|max:2048',
        ]);

        $coverImage = null;
        if ($request->hasFile('cover_image')) {
            $coverImage = $request->file('cover_image')->store('franchises', 'public');
        }

        $franchise = Franchise::create([
            'user_id' => Auth::id(),
            'name' => $validated['name'],
            'cover_image' => $coverImage,
        ]);

        return response()->json($franchise, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $franchise = Franchise::where('user_id', Auth::id())->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'cover_image' => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('cover_image')) {
            if ($franchise->cover_image) {
                Storage::disk('public')->delete($franchise->cover_image);
            }
            $validated['cover_image'] = $request->file('cover_image')->store('franchises', 'public');
        }

        $franchise->update($validated);

        return response()->json($franchise);
    }

    public function destroy(int $id): JsonResponse
    {
        $franchise = Franchise::where('user_id', Auth::id())->findOrFail($id);

        if ($franchise->cover_image) {
            Storage::disk('public')->delete($franchise->cover_image);
        }

        $franchise->delete();

        return response()->json(['message' => 'Franchise deleted']);
    }

    public function listByName(): JsonResponse
    {
        // For the form dropdown — returns simple id/name pairs
        return response()->json(
            Franchise::where('user_id', Auth::id())
                ->orderBy('name')
                ->pluck('name', 'id')
        );
    }
}