<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\CollectionItem;
use App\Models\Game;
use App\Models\Movie;
use App\Models\Music;
use App\Models\TvShow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CollectionController extends Controller
{
    private function formatItem(CollectionItem $item): array
    {
        $typeKey = $item->type;
        $details = $item->$typeKey ?? null;

        return [
            'id' => $item->id,
            'type' => $item->type,
            'title' => $item->title,
            'cover_image' => $item->cover_image,
            'barcode' => $item->barcode,              // <-- ADD THIS
            'purchase_date' => $item->purchase_date?->format('Y-m-d'),
            'purchase_price' => $item->purchase_price,
            'condition' => $item->condition,
            'status' => $item->status,
            'notes' => $item->notes,
            'details' => $details,
            'created_at' => $item->created_at->format('Y-m-d H:i:s'),
        ];
    }

    public function index(Request $request, string $type): JsonResponse
    {
        $validTypes = ['movie', 'book', 'game', 'music', 'tv_show'];
        if (!in_array($type, $validTypes)) {
            return response()->json(['message' => 'Invalid collection type'], 422);
        }

        $query = CollectionItem::where('user_id', Auth::id())
            ->where('type', $type)
            ->with($type);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('format')) {
            // $query->whereHas($type, fn($q) => $q->where('format', $request->format));
            $query->whereHas($type, fn($q) => $q->where('format', $request->input('format')));
        }

        if ($type === 'game' && $request->filled('platform')) {
            $query->whereHas('game', fn($q) => $q->where('platform', $request->platform));
        }

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

        // $sortBy = $request->get('sort_by', 'created_at');
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');
        if (in_array($sortBy, ['title', 'purchase_date', 'purchase_price', 'created_at'])) {
            $query->orderBy($sortBy, $sortDir);
        }

        $items = $query->paginate($request->input('per_page', 24));
        $items->getCollection()->transform(fn($item) => $this->formatItem($item));

        return response()->json($items);
    }

    public function store(Request $request, string $type): JsonResponse
    {
        $validTypes = ['movie', 'book', 'game', 'music'];
        if (!in_array($type, $validTypes)) {
            return response()->json(['message' => 'Invalid collection type'], 422);
        }

        $rules = $this->getValidationRules($type);
        $rules['title'] = 'required|string|max:255';
        $validated = $request->validate($rules);

        return DB::transaction(function () use ($validated, $type, $request) {
            // Handle cover upload
            $coverImage = null;

            // Priority 1: cover already downloaded during barcode lookup
            if ($request->filled('existing_cover')) {
                $coverImage = $request->input('existing_cover');
            }
            // Priority 2: user uploaded a new file
            elseif ($request->hasFile('cover_image')) {
                $coverImage = $request->file('cover_image')->store('covers', 'public');
            }
            // Priority 3: editing and no new cover — keep existing (handled by not setting $coverImage)

            $item = CollectionItem::create([
                'user_id' => Auth::id(),
                'type' => $type,
                'title' => $validated['title'],
                'cover_image' => $coverImage,
                'barcode' => $validated['barcode'] ?? null,  // <-- ADD THIS
                'purchase_date' => $validated['purchase_date'] ?? null,
                'purchase_price' => $validated['purchase_price'] ?? null,
                'condition' => $validated['condition'] ?? 'near_mint',
                'status' => $validated['status'] ?? 'owned',
                'notes' => $validated['notes'] ?? null,
            ]);

            // Extract type-specific fields (everything NOT in the base list)
            $baseFields = ['title', 'cover_image', 'purchase_date', 'purchase_price', 'condition', 'status', 'notes'];
            $detailData = array_filter(
                $validated,
                fn($key) => !in_array($key, $baseFields),
                ARRAY_FILTER_USE_KEY
            );

            $modelClass = match ($type) {
                'movie' => Movie::class,
                'book' => Book::class,
                'game' => Game::class,
                'music' => Music::class,
                'tv_show' => TvShow::class,
            };

            $modelClass::create([
                'collection_item_id' => $item->id,
                ...$detailData,
            ]);

            // Reload with the detail relation
            $item->load($type);

            return response()->json($this->formatItem($item), 201);
        });
    }

    public function show(string $type, int $id): JsonResponse
    {
        $item = CollectionItem::where('user_id', Auth::id())
            ->where('type', $type)
            ->with($type)
            ->findOrFail($id);

        return response()->json($this->formatItem($item));
    }

    public function update(Request $request, string $type, int $id): JsonResponse
    {
        $item = CollectionItem::where('user_id', Auth::id())
            ->where('type', $type)
            ->with($type)
            ->findOrFail($id);

        $rules = $this->getValidationRules($type);
        $rules['title'] = 'sometimes|required|string|max:255';
        $validated = $request->validate($rules);

        return DB::transaction(function () use ($item, $validated, $type, $request) {
            // Handle cover replacement
            $coverImage = $item->cover_image;

            if ($request->filled('existing_cover')) {
                $coverImage = $request->input('existing_cover');
            } elseif ($request->hasFile('cover_image')) {
                if ($item->cover_image) {
                    Storage::disk('public')->delete($item->cover_image);
                }
                $coverImage = $request->file('cover_image')->store('covers', 'public');
            }

            // Single update — no double-save
            $item->update([
                'title' => $validated['title'] ?? $item->title,
                'cover_image' => $coverImage,
                'barcode' => $validated['barcode'] ?? $item->barcode,  // <-- ADD THIS
                'purchase_date' => $validated['purchase_date'] ?? $item->purchase_date,
                'purchase_price' => $validated['purchase_price'] ?? $item->purchase_price,
                'condition' => $validated['condition'] ?? $item->condition,
                'status' => $validated['status'] ?? $item->status,
                'notes' => $validated['notes'] ?? $item->notes,
            ]);

            // Update type-specific detail
            $baseFields = ['title', 'cover_image', 'purchase_date', 'purchase_price', 'condition', 'status', 'notes'];
            $detailData = array_filter(
                $validated,
                fn($key) => !in_array($key, $baseFields),
                ARRAY_FILTER_USE_KEY
            );

            if (!empty($detailData)) {
                $item->$type->update($detailData);
            }

            // Reload fresh
            $item->load($type);

            return response()->json($this->formatItem($item));
        });
    }

    public function destroy(string $type, int $id): JsonResponse
    {
        $item = CollectionItem::where('user_id', Auth::id())
            ->where('type', $type)
            ->findOrFail($id);

        if ($item->cover_image) {
            Storage::disk('public')->delete($item->cover_image);
        }

        // hasOne cascade: delete the detail row first, then the base row
        $item->$type()->delete();
        $item->delete();

        return response()->json(['message' => 'Item deleted successfully']);
    }

    private function getValidationRules(string $type): array
    {
        $base = [
            'barcode' => 'nullable|string|max:20',
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
            'tv_show' => $base + [
                'format' => 'required|in:Digital,DVD,Blu-ray,4K UHD,VHS',
                'total_seasons' => 'nullable|integer|min:1',
                'total_episodes' => 'nullable|integer|min:1',
                'network' => 'nullable|string|max:255',
                'genre' => 'nullable|string|max:255',
                'personal_rating' => 'nullable|integer|min:1|max:10',
                'release_year' => 'nullable|integer|min:1920|max:' . (date('Y') + 2),
                'watch_status' => 'nullable|in:watching,completed,dropped,plan_to_watch',
                'current_season' => 'nullable|integer|min:1',
                'current_episode' => 'nullable|integer|min:1',
            ],
        };
    }
}