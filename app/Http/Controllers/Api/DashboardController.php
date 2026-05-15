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
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function stats(): JsonResponse
    {
        $userId = Auth::id();

        // 1. Base stats
        $totalItems = CollectionItem::where('user_id', $userId)->count();
        $totalValue = CollectionItem::where('user_id', $userId)->sum('purchase_price');

        // 2. Count and value by type
        $byType = CollectionItem::where('user_id', $userId)
            ->selectRaw('type, count(*) as count, sum(purchase_price) as value')
            ->groupBy('type')
            ->get()
            ->keyBy('type');

        // 3. Movies by format
        $movieIds = CollectionItem::where('user_id', $userId)->where('type', 'movie')->pluck('id');
        $moviesByFormat = [];
        if ($movieIds->isNotEmpty()) {
            $moviesByFormat = Movie::whereIn('collection_item_id', $movieIds)
                ->selectRaw('format, count(*) as count')
                ->groupBy('format')
                ->get();
        }

        // 4. Games by platform
        $gameIds = CollectionItem::where('user_id', $userId)->where('type', 'game')->pluck('id');
        $gamesByPlatform = [];
        if ($gameIds->isNotEmpty()) {
            $gamesByPlatform = Game::whereIn('collection_item_id', $gameIds)
                ->selectRaw('platform, count(*) as count')
                ->groupBy('platform')
                ->get();
        }

        // 5. Music by format
        $musicIds = CollectionItem::where('user_id', $userId)->where('type', 'music')->pluck('id');
        $musicByFormat = [];
        if ($musicIds->isNotEmpty()) {
            $musicByFormat = Music::whereIn('collection_item_id', $musicIds)
                ->selectRaw('format, count(*) as count')
                ->groupBy('format')
                ->get();
        }

        // 6. Books read/unread
        $bookIds = CollectionItem::where('user_id', $userId)->where('type', 'book')->pluck('id');
        $booksRead = 0;
        $booksUnread = 0;
        if ($bookIds->isNotEmpty()) {
            $booksRead = Book::whereIn('collection_item_id', $bookIds)->where('read', true)->count();
            $booksUnread = Book::whereIn('collection_item_id', $bookIds)->where('read', false)->count();
        }

        // 7. Games completed
        $gamesCompleted = 0;
        if ($gameIds->isNotEmpty()) {
            $gamesCompleted = Game::whereIn('collection_item_id', $gameIds)->where('completed', true)->count();
        }

        // 8. TV Shows watching/completed
        $tvShowIds = CollectionItem::where('user_id', $userId)->where('type', 'tv_show')->pluck('id');
        $tvShowsWatching = 0;
        $tvShowsCompleted = 0;
        if ($tvShowIds->isNotEmpty()) {
            $tvShowsWatching = TvShow::whereIn('collection_item_id', $tvShowIds)->where('watch_status', 'watching')->count();
            $tvShowsCompleted = TvShow::whereIn('collection_item_id', $tvShowIds)->where('watch_status', 'completed')->count();
        }

        // 9. Recent additions — manual detail loading
        $recentItems = CollectionItem::where('user_id', $userId)
            ->latest()
            ->limit(10)
            ->get();

        $recent = [];
        if ($recentItems->isNotEmpty()) {
            // Group IDs by type
            $idsByType = $recentItems->groupBy('type')->map(fn($items) => $items->pluck('id'));

            $detailsByType = [];

            foreach ($idsByType as $type => $ids) {
                $modelClass = match ($type) {
                    'movie' => Movie::class,
                    'book' => Book::class,
                    'game' => Game::class,
                    'music' => Music::class,
                    'tv_show' => TvShow::class,
                    default => null,
                };

                if ($modelClass) {
                    $query = $modelClass::whereIn('collection_item_id', $ids);
                    if ($type === 'book') {
                        $query->with('series');
                    }
                    $detailsByType[$type] = $query->get()->keyBy('collection_item_id');
                }
            }

            foreach ($recentItems as $item) {
                $typeKey = $item->type;
                $details = $detailsByType[$typeKey][$item->id] ?? null;

                $recent[] = [
                    'id' => $item->id,
                    'type' => $item->type,
                    'title' => $item->title,
                    'cover_image' => $item->cover_image,
                    'barcode' => $item->barcode,
                    'purchase_date' => $item->purchase_date?->format('Y-m-d'),
                    'purchase_price' => $item->purchase_price,
                    'condition' => $item->condition,
                    'status' => $item->status,
                    'notes' => $item->notes,
                    'details' => $details,
                    'created_at' => $item->created_at->format('Y-m-d H:i:s'),
                ];
            }
        }

        // 10. Wishlist count
        $wishlistCount = CollectionItem::where('user_id', $userId)
            ->where('status', 'wishlist')
            ->count();

        // 11. Borrowed out count
        $borrowedCount = CollectionItem::where('user_id', $userId)
            ->where('status', 'borrowed')
            ->count();

        return response()->json([
            'total_items' => $totalItems,
            'total_value' => (float) $totalValue,
            'by_type' => $byType,
            'movies_by_format' => $moviesByFormat,
            'games_by_platform' => $gamesByPlatform,
            'music_by_format' => $musicByFormat,
            'books_read' => $booksRead,
            'books_unread' => $booksUnread,
            'games_completed' => $gamesCompleted,
            'tv_shows_watching' => $tvShowsWatching,
            'tv_shows_completed' => $tvShowsCompleted,
            'recent_additions' => $recent,
            'wishlist_count' => $wishlistCount,
            'rating_distribution' => $this->getRatingDistribution(),
            'loaned_out' => $this->getLoanedOutItems(),
            'borrowed_count' => $borrowedCount,
        ]);
    }

    private function getRatingDistribution(): array
    {
        $userId = Auth::id();
        $tables = ['movies', 'books', 'games', 'music', 'tv_shows'];
        $itemTable = 'collection_items';

        // All rated items across 5 tables
        $rated = collect();
        foreach ($tables as $table) {
            $rows = DB::table($table)
                ->select('personal_rating')
                ->whereExists(
                    fn($q) =>
                    $q->selectRaw(1)
                        ->from($itemTable)
                        ->whereColumn($itemTable . '.id', $table . '.collection_item_id')
                        ->where('user_id', $userId)
                )
                ->whereNotNull('personal_rating')
                ->get();

            $rated = $rated->merge($rows);
        }

        // Build buckets 1-10
        $distribution = [];
        for ($i = 1; $i <= 10; $i++) {
            $distribution[] = [
                'rating' => $i,
                'count' => $rated->where('personal_rating', $i)->count(),
            ];
        }

        // Unrated count: total items minus rated
        $totalItems = DB::table($itemTable)->where('user_id', $userId)->count();
        $distribution[] = [
            'rating' => null,
            'count' => $totalItems - $rated->count(),
        ];

        return $distribution;
    }

    // New method to get loaned out items
    private function getLoanedOutItems(): array
    {
        $items = CollectionItem::where('user_id', Auth::id())
            ->where('status', 'borrowed')
            ->whereNotNull('due_back_date')
            ->orderBy('due_back_date', 'asc')
            ->limit(10)
            ->get();

        $result = [];
        foreach ($items as $item) {
            $dueDate = \Carbon\Carbon::parse($item->due_back_date);
            $daysUntil = now()->diffInDays($dueDate, false);
            $isOverdue = $dueDate->isPast();

            $result[] = [
                'id' => $item->id,
                'type' => $item->type,
                'title' => $item->title,
                'cover_image' => $item->cover_image,
                'borrowed_to' => $item->borrowed_to,
                'due_back_date' => $item->due_back_date,
                'days_until_due' => $daysUntil,
                'is_overdue' => $isOverdue,
            ];
        }

        return $result;
    }
}