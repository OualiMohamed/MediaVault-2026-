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
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    // GET /api/dashboard/stats

    /**
     * The function `stats` retrieves various statistics related to a user's collection items including
     * total count, total value, distribution by type, counts by format/platform, books read vs unread,
     * games completed, recent additions, and wishlist count.
     * 
     * @return JsonResponse The `stats()` function returns a JSON response containing various statistics
     * related to a user's collection items. The returned data includes:
     * - Total number of items in the collection
     * - Total value of all items in the collection
     * - Breakdown of items by type (count and total value for each type)
     * - Number of movies by format
     * - Number of games by platform
     * - Number of music items by
     */
    public function stats(): JsonResponse
    {
        $userId = Auth::id();

        $totalItems = CollectionItem::where('user_id', $userId)->count();
        $totalValue = CollectionItem::where('user_id', $userId)->sum('purchase_price');

        $byType = CollectionItem::where('user_id', $userId)
            ->selectRaw('type, count(*) as count, sum(purchase_price) as value')
            ->groupBy('type')
            ->get()
            ->keyBy('type');

        // Movies by format
        $moviesByFormat = Movie::whereHas('collectionItem', fn($q) => $q->where('user_id', $userId))
            ->selectRaw('format, count(*) as count')
            ->groupBy('format')
            ->get();

        // Games by platform
        $gamesByPlatform = Game::whereHas('collectionItem', fn($q) => $q->where('user_id', $userId))
            ->selectRaw('platform, count(*) as count')
            ->groupBy('platform')
            ->get();

        // Music by format
        $musicByFormat = Music::whereHas('collectionItem', fn($q) => $q->where('user_id', $userId))
            ->selectRaw('format, count(*) as count')
            ->groupBy('format')
            ->get();

        // Books read vs unread
        $booksRead = Book::whereHas('collectionItem', fn($q) => $q->where('user_id', $userId))
            ->where('read', true)->count();
        $booksUnread = Book::whereHas('collectionItem', fn($q) => $q->where('user_id', $userId))
            ->where('read', false)->count();

        // Games completed
        $gamesCompleted = Game::whereHas('collectionItem', fn($q) => $q->where('user_id', $userId))
            ->where('completed', true)->count();

        $tvShowsWatching = TvShow::whereHas('collectionItem', fn($q) => $q->where('user_id', $userId))
            ->where('watch_status', 'watching')->count();
        $tvShowsCompleted = TvShow::whereHas('collectionItem', fn($q) => $q->where('user_id', $userId))
            ->where('watch_status', 'completed')->count();

        // Recent additions (last 10)
        $recent = CollectionItem::where('user_id', $userId)
            ->with(['movie', 'book', 'game', 'music', 'tv_show'])
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn($item) => $this->formatItem($item));

        // Wishlist count
        $wishlistCount = CollectionItem::where('user_id', $userId)
            ->where('status', 'wishlist')
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
        ]);
    }

    // Helper function to format collection item with its details
    private function formatItem(CollectionItem $item): array
    {
        $typeKey = $item->type;
        $details = $item->$typeKey ?? null;

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
}
