<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CollectionItem;
use App\Models\Movie;
use App\Models\Book;
use App\Models\Game;
use App\Models\Music;
use App\Models\TvShow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Carbon;

class CollectionController extends Controller
{
    private function getModelClass(string $type): string
    {
        return match ($type) {
            'movie' => Movie::class,
            'book' => Book::class,
            'game' => Game::class,
            'music' => Music::class,
            'tv_show' => TvShow::class,
            default => Movie::class,
        };
    }

    private function formatItem(CollectionItem $item): array
    {
        $typeKey = $item->type;
        $details = null;

        // Get detail from the manually attached relation
        if ($item->relationLoaded($typeKey)) {
            $details = $item->getRelation($typeKey);
        }

        return [
            'id' => $item->id,
            'type' => $item->type,
            'title' => $item->title,
            'cover_image' => $item->cover_image,
            'barcode' => $item->barcode,
            // 'purchase_date' => $item->purchase_date?->format('Y-m-d'),
            'purchase_date' => $item->purchase_date ? \Carbon\Carbon::parse($item->purchase_date)->format('Y-m-d') : null,
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

        $modelClass = $this->getModelClass($type);
        $detailTable = (new $modelClass)->getTable();

        $query = CollectionItem::where('user_id', Auth::id())
            ->where('type', $type);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // A-Z letter filter
        if ($request->filled('letter')) {
            $letter = $request->letter;
            if ($letter === '#') {
                $query->whereRaw("title NOT REGEXP '^[A-Za-z]'");
            } else {
                $query->where('title', 'LIKE', $letter . '%');
            }
        }

        if ($request->filled('format')) {
            if ($type === 'tv_show') {
                // Search inside the JSON seasons array for matching format
                $query->whereExists(
                    fn($q) =>
                    $q->selectRaw(1)
                        ->from($detailTable)
                        ->whereColumn($detailTable . '.collection_item_id', 'collection_items.id')
                        ->whereRaw(
                            'JSON_SEARCH(' . $detailTable . '.seasons, \'one\', ?, NULL, \'$[*].format\') IS NOT NULL',
                            [$request->input('format')]
                        )
                );
            } else {
                $query->whereExists(
                    fn($q) =>
                    $q->selectRaw(1)
                        ->from($detailTable)
                        ->whereColumn($detailTable . '.collection_item_id', 'collection_items.id')
                        ->where('format', $request->input('format'))
                );
            }
        }

        // Genre filter — works for all types that have a genre column
        if ($request->filled('genre') && in_array($type, ['book', 'music', 'movie', 'tv_show', 'game'])) {
            $query->whereExists(
                fn($q) =>
                $q->selectRaw(1)
                    ->from($detailTable)
                    ->whereColumn($detailTable . '.collection_item_id', 'collection_items.id')
                    ->whereRaw('LOWER(' . $detailTable . '.genre) LIKE ?', ['%' . strtolower($request->genre) . '%'])
            );
        }

        // if ($type === 'book' && $request->filled('genre')) {
        //     $query->whereExists(
        //         fn($q) =>
        //         $q->selectRaw(1)
        //             ->from($detailTable)
        //             ->whereColumn($detailTable . '.collection_item_id', 'collection_items.id')
        //             ->whereRaw('LOWER(' . $detailTable . '.genre) LIKE ?', ['%' . strtolower($request->genre) . '%'])
        //     );
        // }

        // // genre filter for music
        // if ($type === 'music' && $request->filled('genre')) {
        //     $query->whereExists(
        //         fn($q) =>
        //         $q->selectRaw(1)
        //             ->from($detailTable)
        //             ->whereColumn($detailTable . '.collection_item_id', 'collection_items.id')
        //             ->whereRaw('LOWER(' . $detailTable . '.genre) LIKE ?', ['%' . strtolower($request->genre) . '%'])
        //     );
        // }

        if ($type === 'game' && $request->filled('platform')) {
            $query->whereExists(
                fn($q) =>
                $q->selectRaw(1)
                    ->from($detailTable)
                    ->whereColumn($detailTable . '.collection_item_id', 'collection_items.id')
                    ->where('platform', $request->platform)
            );
        }

        if ($type === 'tv_show' && $request->filled('watch_status')) {
            $query->whereExists(
                fn($q) =>
                $q->selectRaw(1)
                    ->from($detailTable)
                    ->whereColumn($detailTable . '.collection_item_id', 'collection_items.id')
                    ->where('watch_status', $request->watch_status)
            );
        }

        if ($type === 'movie') {
            if ($request->filled('video_quality')) {
                $query->whereExists(
                    fn($q) =>
                    $q->selectRaw(1)
                        ->from($detailTable)
                        ->whereColumn($detailTable . '.collection_item_id', 'collection_items.id')
                        ->where('video_quality', $request->video_quality)
                );
            }
            if ($type === 'movie' && $request->filled('audio_format')) {
                $query->whereExists(
                    fn($q) =>
                    $q->selectRaw(1)
                        ->from($detailTable)
                        ->whereColumn($detailTable . '.collection_item_id', 'collection_items.id')
                        ->whereRaw(
                            'JSON_SEARCH(' . $detailTable . '.audio_format, \'one\', ?) IS NOT NULL',
                            [$request->audio_format]
                        )
                );
            }
            if ($request->filled('language')) {
                $query->whereExists(
                    fn($q) =>
                    $q->selectRaw(1)
                        ->from($detailTable)
                        ->whereColumn($detailTable . '.collection_item_id', 'collection_items.id')
                        ->where('language', $request->language)
                );
            }
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $like = '%' . strtolower($search) . '%';

            $query->where(function ($q) use ($like, $type, $detailTable) {
                $q->whereRaw('LOWER(title) LIKE ?', [$like]);

                if ($type === 'book') {
                    $q->orWhereExists(
                        fn($sq) =>
                        $sq->selectRaw(1)
                            ->from($detailTable)
                            ->whereColumn($detailTable . '.collection_item_id', 'collection_items.id')
                            ->whereRaw('LOWER(author) LIKE ?', [$like])
                    );
                }

                if ($type === 'movie' || $type === 'tv_show') {
                    $q->orWhereExists(
                        fn($sq) =>
                        $sq->selectRaw(1)
                            ->from($detailTable)
                            ->whereColumn($detailTable . '.collection_item_id', 'collection_items.id')
                            ->whereRaw('LOWER(director) LIKE ?', [$like])
                    );

                    // Case-insensitive JSON search via LOWER string match
                    $q->orWhereExists(
                        fn($sq) =>
                        $sq->selectRaw(1)
                            ->from($detailTable)
                            ->whereColumn($detailTable . '.collection_item_id', 'collection_items.id')
                            ->whereRaw('LOWER(CAST(' . $detailTable . '.actors AS CHAR)) LIKE ?', [$like])
                    );
                }

                if ($type === 'music') {
                    $q->orWhereExists(
                        fn($sq) =>
                        $sq->selectRaw(1)
                            ->from($detailTable)
                            ->whereColumn($detailTable . '.collection_item_id', 'collection_items.id')
                            ->whereRaw('LOWER(artist) LIKE ?', [$like])
                    );
                }
            });
        }

        // $sortBy = $request->get('sort_by', 'created_at');
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');
        if ($sortBy === 'title') {
            $query->orderByRaw('LOWER(title) ' . ($sortDir === 'desc' ? 'DESC' : 'ASC'));
        } elseif (in_array($sortBy, ['purchase_date', 'purchase_price', 'created_at'])) {
            $query->orderBy($sortBy, $sortDir);
        }

        $items = $query->paginate($request->input('per_page', 24));

        if ($items->isNotEmpty()) {
            $ids = $items->pluck('id');
            $details = $modelClass::whereIn('collection_item_id', $ids);
            if ($type === 'book') {
                $details->with('series');
            }
            $details = $modelClass::whereIn('collection_item_id', $ids)
                ->with('franchise', 'series')
                ->get()
                ->keyBy('collection_item_id');
            $items->each(fn($item) => $item->setRelation($type, $details->get($item->id)));
            $items->getCollection()->transform(fn($item) => $this->formatItem($item));
        }


        return response()->json($items);
    }

    public function store(Request $request, string $type): JsonResponse
    {
        $validTypes = ['movie', 'book', 'game', 'music', 'tv_show'];
        if (!in_array($type, $validTypes)) {
            return response()->json(['message' => 'Invalid collection type'], 422);
        }

        $rules = $this->getValidationRules($type);
        $rules['title'] = 'required|string|max:255';
        $validated = $request->validate($rules);

        return DB::transaction(function () use ($validated, $type, $request) {
            $coverImage = null;
            if ($request->filled('existing_cover')) {
                $coverImage = $request->input('existing_cover');
            } elseif ($request->hasFile('cover_image')) {
                $coverImage = $request->file('cover_image')->store('covers', 'public');
            }

            $item = CollectionItem::create([
                'user_id' => Auth::id(),
                'type' => $type,
                'title' => $validated['title'],
                'cover_image' => $coverImage,
                'barcode' => $validated['barcode'] ?? null,
                'purchase_date' => $validated['purchase_date'] ?? null,
                'purchase_price' => $validated['purchase_price'] ?? null,
                'condition' => $validated['condition'] ?? 'near_mint',
                'status' => $validated['status'] ?? 'owned',
                'notes' => $validated['notes'] ?? null,
            ]);

            $baseFields = ['title', 'barcode', 'cover_image', 'purchase_date', 'purchase_price', 'condition', 'status', 'notes', 'series_name', 'franchise_name'];

            $detailData = array_filter(
                $validated,
                fn($key) => !in_array($key, $baseFields),
                ARRAY_FILTER_USE_KEY
            );

            // $detailData = array_filter(
            //     $validated,
            //     fn($key) => !in_array($key, $baseFields),
            //     ARRAY_FILTER_USE_KEY
            // );

            // DECODE SEASONS JSON — ADD THESE LINES:
            if (isset($validated['seasons'])) {
                $decoded = json_decode($validated['seasons'], true);
                if (is_array($decoded)) {
                    $detailData['seasons'] = $decoded;
                }
            }

            // Handle series — convert name to series_id
            if ($type === 'book') {
                if (!empty($validated['series_name'])) {
                    $series = \App\Models\BookSeries::firstOrCreate(
                        ['user_id' => Auth::id(), 'name' => trim($validated['series_name'])],
                    );
                    $detailData['series_id'] = $series->id;
                    $detailData['series_position'] = !empty($validated['series_position']) ? (int) $validated['series_position'] : null;
                } else {
                    $detailData['series_id'] = null;
                    $detailData['series_position'] = null;
                }
            }

            // Handle franchise — convert name to franchise_id
            if (!empty($validated['franchise_name'])) {
                $franchise = \App\Models\Franchise::firstOrCreate(
                    ['user_id' => Auth::id(), 'name' => trim($validated['franchise_name'])],
                );
                $detailData['franchise_id'] = $franchise->id;
                $detailData['franchise_position'] = !empty($validated['franchise_position']) ? (int) $validated['franchise_position'] : null;
            } else {
                $detailData['franchise_id'] = null;
                $detailData['franchise_position'] = null;
            }

            // HANDLE ACTORS (Convert comma-separated string to JSON array for manual entry)
            if (isset($detailData['actors']) && is_string($detailData['actors'])) {
                $names = explode(',', $detailData['actors']);
                $detailData['actors'] = array_map(fn($n) => ['name' => trim($n)], array_filter($names));
            }

            // // Handle series
            // if (!empty($validated['series_name'])) {
            //     $series = \App\Models\BookSeries::firstOrCreate(
            //         ['user_id' => Auth::id(), 'name' => trim($validated['series_name'])],
            //     );
            //     $detailData['series_id'] = $series->id;
            //     $detailData['series_position'] = !empty($validated['series_position']) ? (int) $validated['series_position'] : null;
            // } else {
            //     $detailData['series_id'] = null;
            //     $detailData['series_position'] = null;
            // }

            $modelClass = $this->getModelClass($type);
            $detail = $modelClass::create([
                'collection_item_id' => $item->id,
                ...$detailData,
            ]);

            $detail = $modelClass::where('collection_item_id', $item->id)
                ->with('franchise', 'series')
                ->first();

            // Attach detail manually — NO ->load()
            $item->setRelation($type, $detail);

            return response()->json($this->formatItem($item), 201);
        });
    }

    public function show(string $type, int $id): JsonResponse
    {
        $validTypes = ['movie', 'book', 'game', 'music', 'tv_show'];
        if (!in_array($type, $validTypes)) {
            return response()->json(['message' => 'Invalid collection type'], 422);
        }

        $item = CollectionItem::where('user_id', Auth::id())
            ->where('type', $type)
            ->findOrFail($id);

        // Fetch detail manually — NO ->load()
        $modelClass = $this->getModelClass($type);
        $detailQuery = $modelClass::where('collection_item_id', $item->id);
        if ($type === 'book') {
            $detailQuery->with('series');
        }
        $detail = $detailQuery->first();
        $item->setRelation($type, $detail);

        return response()->json($this->formatItem($item));
    }

    public function update(Request $request, string $type, int $id): JsonResponse
    {
        $validTypes = ['movie', 'book', 'game', 'music', 'tv_show'];
        if (!in_array($type, $validTypes)) {
            return response()->json(['message' => 'Invalid collection type'], 422);
        }

        $item = CollectionItem::where('user_id', Auth::id())
            ->where('type', $type)
            ->findOrFail($id);

        $rules = $this->getValidationRules($type);
        $rules['title'] = 'sometimes|required|string|max:255';
        $validated = $request->validate($rules);

        return DB::transaction(function () use ($item, $validated, $type, $request) {
            $coverImage = $item->cover_image;

            if ($request->filled('existing_cover')) {
                $coverImage = $request->input('existing_cover');
            } elseif ($request->hasFile('cover_image')) {
                if ($item->cover_image) {
                    Storage::disk('public')->delete($item->cover_image);
                }
                $coverImage = $request->file('cover_image')->store('covers', 'public');
            }

            $item->update([
                'title' => $validated['title'] ?? $item->title,
                'cover_image' => $coverImage,
                'barcode' => $validated['barcode'] ?? $item->barcode,
                'purchase_date' => $validated['purchase_date'] ?? $item->purchase_date,
                'purchase_price' => $validated['purchase_price'] ?? $item->purchase_price,
                'condition' => $validated['condition'] ?? $item->condition,
                'status' => $validated['status'] ?? $item->status,
                'notes' => $validated['notes'] ?? $item->notes,
            ]);

            $baseFields = ['title', 'barcode', 'cover_image', 'purchase_date', 'purchase_price', 'condition', 'status', 'notes', 'series_name'];

            $detailData = array_filter(
                $validated,
                fn($key) => !in_array($key, $baseFields),
                ARRAY_FILTER_USE_KEY
            );

            // $detailData = array_filter(
            //     $validated,
            //     fn($key) => !in_array($key, $baseFields),
            //     ARRAY_FILTER_USE_KEY
            // );

            // DECODE SEASONS JSON — ADD THESE LINES:
            if (isset($validated['seasons'])) {
                $decoded = json_decode($validated['seasons'], true);
                if (is_array($decoded)) {
                    $detailData['seasons'] = $decoded;
                }
            }

            // Handle series — convert name to series_id
            if ($type === 'book') {
                if (!empty($validated['series_name'])) {
                    $series = \App\Models\BookSeries::firstOrCreate(
                        ['user_id' => Auth::id(), 'name' => trim($validated['series_name'])],
                    );
                    $detailData['series_id'] = $series->id;
                    $detailData['series_position'] = !empty($validated['series_position']) ? (int) $validated['series_position'] : null;
                } else {
                    $detailData['series_id'] = null;
                    $detailData['series_position'] = null;
                }
            }

            // Handle franchise — convert name to franchise_id
            if (!empty($validated['franchise_name'])) {
                $franchise = \App\Models\Franchise::firstOrCreate(
                    ['user_id' => Auth::id(), 'name' => trim($validated['franchise_name'])],
                );
                $detailData['franchise_id'] = $franchise->id;
                $detailData['franchise_position'] = !empty($validated['franchise_position']) ? (int) $validated['franchise_position'] : null;
            } else {
                $detailData['franchise_id'] = null;
                $detailData['franchise_position'] = null;
            }

            // HANDLE ACTORS (Convert comma-separated string to JSON array)
            if (isset($detailData['actors']) && is_string($detailData['actors'])) {
                $names = explode(',', $detailData['actors']);
                $detailData['actors'] = array_map(fn($n) => ['name' => trim($n)], array_filter($names));
            }

            if (!empty($detailData)) {
                $modelClass = $this->getModelClass($type);
                $modelClass::where('collection_item_id', $item->id)->update($detailData);
            }

            // Same series handling block as store, inside the transaction
            // if (!empty($validated['series_name'])) {
            //     $series = \App\Models\BookSeries::firstOrCreate(
            //         ['user_id' => Auth::id(), 'name' => trim($validated['series_name'])],
            //     );
            //     $detailData['series_id'] = $series->id;
            //     $detailData['series_position'] = !empty($validated['series_position']) ? (int) $validated['series_position'] : null;
            // } else {
            //     $detailData['series_id'] = null;
            //     $detailData['series_position'] = null;
            // }

            // Fetch detail manually — NO ->load()
            $modelClass = $this->getModelClass($type);
            $detailQuery = $modelClass::where('collection_item_id', $item->id);
            if ($type === 'book') {
                $detailQuery->with('franchise', 'series');
            }
            $detail = $detailQuery->first();
            $item->setRelation($type, $detail);

            return response()->json($this->formatItem($item));
        });
    }

    public function destroy(string $type, int $id): JsonResponse
    {
        $validTypes = ['movie', 'book', 'game', 'music', 'tv_show'];
        if (!in_array($type, $validTypes)) {
            return response()->json(['message' => 'Invalid collection type'], 422);
        }

        $item = CollectionItem::where('user_id', Auth::id())
            ->where('type', $type)
            ->findOrFail($id);

        if ($item->cover_image) {
            Storage::disk('public')->delete($item->cover_image);
        }

        // Delete detail manually — NO ->load()
        $modelClass = $this->getModelClass($type);
        $modelClass::where('collection_item_id', $item->id)->delete();
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
                'trailer_url' => 'nullable|url|max:500',
                'seen' => 'nullable|boolean',          // add
                'date_seen' => 'nullable|date',         // add
                'video_quality' => 'nullable|string|max:50',   // add
                'audio_format' => 'nullable|json',
                'language' => 'nullable|string|max:50',         // add
                'actors' => 'nullable|string|max:2000',  // add this
                'franchise_name' => 'nullable|string|max:255',
                'franchise_position' => 'nullable|integer|min:1',
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
                'series_name' => 'nullable|string|max:255',
                'series_position' => 'nullable|integer|min:1',
                'franchise_name' => 'nullable|string|max:255',
                'franchise_position' => 'nullable|integer|min:1',
            ],
            'game' => $base + [
                'platform' => 'required|in:PS5,PS4,PS3,PS Vita,Switch,Wii U,Wii,Nintendo DS,Xbox Series X,Xbox One,PC,Steam,Other',
                'format' => 'required|in:Physical,Digital',
                'genre' => 'nullable|string|max:255',
                'publisher' => 'nullable|string|max:255',
                'personal_rating' => 'nullable|integer|min:1|max:10',
                'release_year' => 'nullable|integer|min:1970|max:' . (date('Y') + 2),
                'completed' => 'nullable|boolean',
                'completion_date' => 'nullable|date',
                'franchise_name' => 'nullable|string|max:255',
                'franchise_position' => 'nullable|integer|min:1',
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
                'tracks' => 'nullable|json', // Add this
                'franchise_name' => 'nullable|string|max:255',
                'franchise_position' => 'nullable|integer|min:1',
            ],
            'tv_show' => $base + [
                'total_seasons' => 'nullable|integer|min:1',
                'total_episodes' => 'nullable|integer|min:1',
                'network' => 'nullable|string|max:255',
                'director' => 'nullable|string|max:255', // Add this
                'genre' => 'nullable|string|max:255',
                'personal_rating' => 'nullable|integer|min:1|max:10',
                'release_year' => 'nullable|integer|min:1920|max:' . (date('Y') + 2),
                'watch_status' => 'nullable|in:watching,completed,dropped,plan_to_watch',
                'current_season' => 'nullable|integer|min:1',
                'current_episode' => 'nullable|integer|min:1',
                'seasons' => 'nullable|json',
                'trailer_url' => 'nullable|url|max:500',
                'actors' => 'nullable|string|max:2000',  // add this
                'network_logo' => 'nullable|string|max:255',
                'franchise_name' => 'nullable|string|max:255',
                'franchise_position' => 'nullable|integer|min:1',
            ],
        };
    }

    public function bookGenres(): JsonResponse
    {
        $genres = Book::whereExists(
            fn($q) =>
            $q->selectRaw(1)
                ->from('collection_items')
                ->whereColumn('collection_items.id', 'books.collection_item_id')
                ->where('user_id', Auth::id())
        )
            ->whereNotNull('genre')
            ->where('genre', '!=', '')
            ->pluck('genre')
            ->flatMap(fn($g) => explode(',', $g))
            ->map(fn($g) => trim($g))
            ->filter()
            ->unique()
            ->sort()
            ->values();

        return response()->json($genres);
    }

    // Global search across all collections
    public function globalSearch(Request $request): JsonResponse
    {
        $validated = $request->validate(['q' => 'required|string|min:2|max:100']);
        $like = '%' . strtolower($validated['q']) . '%';

        $query = CollectionItem::where('user_id', Auth::id())
            ->where(function ($q) use ($like) {
                $q->whereRaw('LOWER(title) LIKE ?', [$like]);

                // Movies
                $q->orWhereExists(
                    fn($sq) =>
                    $sq->selectRaw(1)->from('movies')
                        ->whereColumn('movies.collection_item_id', 'collection_items.id')
                        ->whereRaw('LOWER(director) LIKE ?', [$like])
                )->orWhereExists(
                        fn($sq) =>
                        $sq->selectRaw(1)->from('movies')
                            ->whereColumn('movies.collection_item_id', 'collection_items.id')
                            ->whereRaw('LOWER(CAST(actors AS CHAR)) LIKE ?', [$like])
                    );

                // TV Shows
                $q->orWhereExists(
                    fn($sq) =>
                    $sq->selectRaw(1)->from('tv_shows')
                        ->whereColumn('tv_shows.collection_item_id', 'collection_items.id')
                        ->whereRaw('LOWER(director) LIKE ?', [$like])
                )->orWhereExists(
                        fn($sq) =>
                        $sq->selectRaw(1)->from('tv_shows')
                            ->whereColumn('tv_shows.collection_item_id', 'collection_items.id')
                            ->whereRaw('LOWER(CAST(actors AS CHAR)) LIKE ?', [$like])
                    );

                // Books
                $q->orWhereExists(
                    fn($sq) =>
                    $sq->selectRaw(1)->from('books')
                        ->whereColumn('books.collection_item_id', 'collection_items.id')
                        ->whereRaw('LOWER(author) LIKE ?', [$like])
                );

                // Music
                $q->orWhereExists(
                    fn($sq) =>
                    $sq->selectRaw(1)->from('music')
                        ->whereColumn('music.collection_item_id', 'collection_items.id')
                        ->whereRaw('LOWER(artist) LIKE ?', [$like])
                );
            })
            ->orderBy('title', 'asc')
            ->limit(25);

        $items = $query->get(['id', 'type', 'title', 'cover_image']);

        if ($items->isEmpty())
            return response()->json([]);

        $ids = $items->pluck('id');

        // Bulk fetch all details to avoid N+1 queries
        $details = [
            'movie' => Movie::whereIn('collection_item_id', $ids)->get()->keyBy('collection_item_id'),
            'book' => Book::whereIn('collection_item_id', $ids)->get()->keyBy('collection_item_id'),
            'game' => Game::whereIn('collection_item_id', $ids)->get()->keyBy('collection_item_id'),
            'music' => Music::whereIn('collection_item_id', $ids)->get()->keyBy('collection_item_id'),
            'tv_show' => TvShow::whereIn('collection_item_id', $ids)->get()->keyBy('collection_item_id'),
        ];

        $typeRoutes = [
            'movie' => '/movie',
            'book' => '/book',
            'game' => '/game',
            'music' => '/music',
            'tv_show' => '/tv-shows',
        ];

        return response()->json($items->map(function ($item) use ($details, $typeRoutes) {
            $d = $details[$item->type]->get($item->id);
            $subtitle = '';

            if ($item->type === 'movie')
                $subtitle = $d->director ?? '';
            if ($item->type === 'tv_show')
                $subtitle = $d->network ?? ($d->director ?? '');
            if ($item->type === 'book')
                $subtitle = $d->author ?? '';
            if ($item->type === 'music')
                $subtitle = $d->artist ?? '';
            if ($item->type === 'game')
                $subtitle = $d->platform ?? '';

            return [
                'id' => $item->id,
                'type' => $item->type,
                'title' => $item->title,
                'cover_image' => $item->cover_image ? '/storage/' . $item->cover_image : null,
                'subtitle' => $subtitle,
                'url' => ($typeRoutes[$item->type] ?? '/') . '/' . $item->id,
            ];
        }));
    }

    // New method to get unique music genres for the authenticated user
    public function musicGenres(): JsonResponse
    {
        $genres = Music::whereExists(
            fn($q) =>
            $q->selectRaw(1)
                ->from('collection_items')
                ->whereColumn('collection_items.id', 'music.collection_item_id')
                ->where('user_id', Auth::id())
        )
            ->whereNotNull('genre')
            ->where('genre', '!=', '')
            ->pluck('genre')
            ->flatMap(fn($g) => explode(',', $g))
            ->map(fn($g) => trim($g))
            ->filter()
            ->unique()
            ->sort()
            ->values();

        return response()->json($genres);
    }

    //   
    public function seriesBooks(int $seriesId): JsonResponse
    {
        $series = \App\Models\BookSeries::where('user_id', Auth::id())
            ->findOrFail($seriesId);

        $books = $series->books()->with('collectionItem')->get()->map(function ($book) {
            $item = $book->collectionItem;
            return [
                'id' => $item->id,
                'title' => $item->title,
                'cover_image' => $item->cover_image,
                'series_position' => $book->series_position,
                'read' => $book->read,
            ];
        });

        return response()->json([
            'series' => $series->name,
            'books' => $books,
        ]);
    }

    public function movieGenres(): JsonResponse
    {
        $genres = Movie::whereExists(
            fn($q) =>
            $q->selectRaw(1)
                ->from('collection_items')
                ->whereColumn('collection_items.id', 'movies.collection_item_id')
                ->where('user_id', Auth::id())
        )
            ->whereNotNull('genre')
            ->where('genre', '!=', '')
            ->pluck('genre')
            ->flatMap(fn($g) => explode(',', $g))
            ->map(fn($g) => trim($g))
            ->filter()
            ->unique()
            ->sort()
            ->values();

        return response()->json($genres);
    }

    public function tvShowGenres(): JsonResponse
    {
        $genres = TvShow::whereExists(
            fn($q) =>
            $q->selectRaw(1)
                ->from('collection_items')
                ->whereColumn('collection_items.id', 'tv_shows.collection_item_id')
                ->where('user_id', Auth::id())
        )
            ->whereNotNull('genre')
            ->where('genre', '!=', '')
            ->pluck('genre')
            ->flatMap(fn($g) => explode(',', $g))
            ->map(fn($g) => trim($g))
            ->filter()
            ->unique()
            ->sort()
            ->values();

        return response()->json($genres);
    }

    public function gameGenres(): JsonResponse
    {
        $genres = Game::whereExists(
            fn($q) =>
            $q->selectRaw(1)
                ->from('collection_items')
                ->whereColumn('collection_items.id', 'games.collection_item_id')
                ->where('user_id', Auth::id())
        )
            ->whereNotNull('genre')
            ->where('genre', '!=', '')
            ->pluck('genre')
            ->flatMap(fn($g) => explode(',', $g))
            ->map(fn($g) => trim($g))
            ->filter()
            ->unique()
            ->sort()
            ->values();

        return response()->json($genres);
    }
}