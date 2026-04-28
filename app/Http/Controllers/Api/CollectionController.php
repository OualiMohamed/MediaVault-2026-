<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CollectionItem;
use App\Models\Movie;
use App\Models\Book;
use App\Models\Game;
use App\Models\Music;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CollectionController extends Controller
{
    // format collection item with its details
    private function formatItem(CollectionItem $item): array
    {
        $details = $item->{$item->type};
        return [
            'id' => $item->id,
            'type' => $item->type,
            'title' => $item->title,
            'cover_image' => $item->cover_image,
            'purchase_date' => $item->purchase_date?->format('Y-m-d'),
            'purchase_price' => $item->purchase_price,
            'condition' => $item->condition,
            'status' => $item->status,
            'notes' => $item->notes,
            'details' => $details,
            'created_at' => $item->created_at->format('Y-m-d H:i:s'),
        ];
    }
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, string $type): JsonResponse
    {
        $validTypes = ['movie', 'book', 'game', 'music'];
        if (!in_array($type, $validTypes)) {
            return response()->json(['message' => 'Invalid collection type'], 422);
        }

        $query = CollectionItem::where('user_id', Auth::id())
            ->where('type', $type)
            ->with($type);

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by format (type-specific)
        if ($request->filled('format')) {
            $format = $request->input('format');
            // $query->whereHas($type, fn($q) => $q->where('format', $request->format));
            $query->whereHas($type, fn($q) => $q->where('format', $format));
        }

        // Filter by platform (games only)
        if ($type === 'game' && $request->filled('platform')) {
            $query->whereHas('game', fn($q) => $q->where('platform', $request->platform));
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search, $type) {
                $q->where('title', 'LIKE', "%{$search}%");
                if ($type === 'book') {
                    $q->orWhereHas('book', fn($sq) => $sq->where('author', 'LIKE', "%{$search}%"));
                }
                if ($type === 'movie') {
                    $q->orWhereHas('movie', fn($sq) => $sq->where('director', 'LIKE', "%{$search}%"));
                }
                if ($type === 'music') {
                    $q->orWhereHas('music', fn($sq) => $sq->where('artist', 'LIKE', "%{$search}%"));
                }
            });
        }

        // Sort
        // $sortBy = $request->get('sort_by', 'created_at');
        $sortBy = $request->input('sort_by', 'created_at');
        // $sortDir = $request->get('sort_dir', 'desc')
        $sortDir = $request->input('sort_dir', 'desc');
        $allowedSorts = ['title', 'purchase_date', 'purchase_price', 'created_at'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortDir);
        }

        // $items = $query->paginate($request->get('per_page', 24));
        $items = $query->paginate($request->input('per_page', 24));

        $items->getCollection()->transform(fn($item) => $this->formatItem($item));

        return response()->json($items);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, string $type): JsonResponse
    {
        $validTypes = ['movie', 'book', 'game', 'music'];
        if (!in_array($type, $validTypes)) {
            return response()->json(['message' => 'Invalid collection type'], 422);
        }

        $rules = $this->getValidationRules($type);

        // For store, title is required
        $rules['title'] = 'required|string|max:255';

        $validated = $request->validate($rules);

        return DB::transaction(function () use ($validated, $type, $request) {
            $coverImage = null;
            if ($request->hasFile('cover_image')) {
                $coverImage = $request->file('cover_image')->store('covers', 'public');
            }

            $item = CollectionItem::create([
                'user_id' => Auth::id(),
                'type' => $type,
                'title' => $validated['title'],
                'cover_image' => $coverImage,
                'purchase_date' => $validated['purchase_date'] ?? null,
                'purchase_price' => $validated['purchase_price'] ?? null,
                'condition' => $validated['condition'] ?? 'near_mint',
                'status' => $validated['status'] ?? 'owned',
                'notes' => $validated['notes'] ?? null,
            ]);

            $detailData = array_filter(
                $validated,
                fn($key) => !in_array($key, ['title', 'cover_image', 'purchase_date', 'purchase_price', 'condition', 'status', 'notes']),
                ARRAY_FILTER_USE_KEY
            );

            $modelClass = match ($type) {
                'movie' => Movie::class,
                'book' => Book::class,
                'game' => Game::class,
                'music' => Music::class,
            };

            $modelClass::create([
                'collection_item_id' => $item->id,
                ...$detailData,
            ]);

            $item->load($type);

            return response()->json($this->formatItem($item), 201);
        });
    }

    /**
     * Display the specified resource.
     */
    public function show(string $type, int $id): JsonResponse
    {
        $item = CollectionItem::where('user_id', Auth::id())
            ->where('type', $type)
            ->with($type)
            ->findOrFail($id);

        return response()->json($this->formatItem($item));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $type, int $id): JsonResponse
    {
        $item = CollectionItem::where('user_id', Auth::id())
            ->where('type', $type)
            ->with($type)
            ->findOrFail($id);

        $rules = $this->getValidationRules($type);
        // For update, all fields optional
        $rules['title'] = 'sometimes|string|max:255';

        $validated = $request->validate($rules);

        return DB::transaction(function () use ($item, $validated, $type, $request) {
            if ($request->hasFile('cover_image')) {
                if ($item->cover_image) {
                    Storage::disk('public')->delete($item->cover_image);
                }
                $item->cover_image = $request->file('cover_image')->store('covers', 'public');
            }

            $item->update([
                'title' => $validated['title'] ?? $item->title,
                'purchase_date' => $validated['purchase_date'] ?? $item->purchase_date,
                'purchase_price' => $validated['purchase_price'] ?? $item->purchase_price,
                'condition' => $validated['condition'] ?? $item->condition,
                'status' => $validated['status'] ?? $item->status,
                'notes' => $validated['notes'] ?? $item->notes,
            ]);

            if (isset($item->cover_image)) {
                $item->save();
            }

            $detailData = array_filter(
                $validated,
                fn($key) => !in_array($key, ['title', 'cover_image', 'purchase_date', 'purchase_price', 'condition', 'status', 'notes']),
                ARRAY_FILTER_USE_KEY
            );

            if (!empty($detailData)) {
                $item->{$type}->update($detailData);
            }

            $item->load($type);

            return response()->json($this->formatItem($item));
        });
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $type, int $id): JsonResponse
    {
        // Ensure the item belongs to the user and matches the type
        $item = CollectionItem::where('user_id', Auth::id())
            ->where('type', $type)
            ->findOrFail($id);

        if ($item->cover_image) {
            Storage::disk('public')->delete($item->cover_image);
        }

        $item->delete();

        return response()->json(['message' => 'Item deleted successfully']);
    }

    // Get validation rules based on type
    private function getValidationRules(string $type): array
    {
        $base = [
            'cover_image' => 'nullable|image|max:2048',
            'purchase_date' => 'nullable|date',
            'purchase_price' => 'nullable|numeric|min:0|max:999999.99',
            'condition' => 'nullable|in:mint,near_mint,good,fair,poor',
            'status' => 'nullable|in:owned,wishlist,borrowed,sold,lost',
            'notes' => 'nullable|string|max:2000',
        ];

        return match ($type) {
            'movie' => $base + [
                'format' => 'required|in:DVD,Blu-ray,4K UHD,Digital,VHS',
                'runtime_minutes' => 'nullable|integer|min:1',
                'director' => 'nullable|string|max:255',
                'genre' => 'nullable|string|max:255',
                'personal_rating' => 'nullable|integer|min:1|max:10',
                'release_year' => 'nullable|integer|min:1888|max:' . (date('Y') + 2),
                'imdb_id' => 'nullable|string|max:20',
            ],
            'book' => $base + [
                'author' => 'required|string|max:255',
                'isbn' => 'nullable|string|max:20',
                'page_count' => 'nullable|integer|min:1',
                'publisher' => 'nullable|string|max:255',
                'genre' => 'nullable|string|max:255',
                'personal_rating' => 'nullable|integer|min:1|max:10',
                'release_year' => 'nullable|integer|min:1000|max:' . (date('Y') + 2),
                'read' => 'nullable|boolean',
                'date_finished' => 'nullable|date',
            ],
            'game' => $base + [
                'platform' => 'required|in:PS5,PS4,PS3,PS Vita,Switch,Wii U,Wii,Xbox Series X,Xbox One,PC,Steam,Other',
                'format' => 'required|in:Physical,Digital',
                'genre' => 'nullable|string|max:255',
                'publisher' => 'nullable|string|max:255',
                'personal_rating' => 'nullable|integer|min:1|max:10',
                'release_year' => 'nullable|integer|min:1970|max:' . (date('Y') + 2),
                'completed' => 'nullable|boolean',
                'completion_date' => 'nullable|date',
            ],
            'music' => $base + [
                'format' => 'required|in:CD,Vinyl,Digital,Cassette,8-Track',
                'artist' => 'required|string|max:255',
                'genre' => 'nullable|string|max:255',
                'label' => 'nullable|string|max:255',
                'track_count' => 'nullable|integer|min:1',
                'personal_rating' => 'nullable|integer|min:1|max:10',
                'release_year' => 'nullable|integer|min:1887|max:' . (date('Y') + 2),
                'vinyl_speed' => 'nullable|in:33,45,78',
            ],
        };
    }
}
