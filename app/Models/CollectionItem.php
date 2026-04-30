<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CollectionItem extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'title',
        'cover_image',
        'barcode',
        'purchase_date',
        'purchase_price',
        'condition',
        'status',
        'notes',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'purchase_price' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ── hasOne, NOT morphOne ──
    public function movie(): HasOne
    {
        return $this->hasOne(Movie::class, 'collection_item_id');
    }

    public function book(): HasOne
    {
        return $this->hasOne(Book::class, 'collection_item_id');
    }

    public function game(): HasOne
    {
        return $this->hasOne(Game::class, 'collection_item_id');
    }

    public function music(): HasOne
    {
        return $this->hasOne(Music::class, 'collection_item_id');
    }

    // app/Models/CollectionItem.php — add tv_show relationship
    public function tvShow(): HasOne
    {
        return $this->hasOne(TvShow::class, 'collection_item_id');
    }

    /**
     * Dynamic accessor — returns the correct detail relation
     * based on $this->type. Used by the API formatter.
     */
    public function details(): HasOne
    {
        return match ($this->type) {
            'movie' => $this->hasOne(Movie::class, 'collection_item_id'),
            'book' => $this->hasOne(Book::class, 'collection_item_id'),
            'game' => $this->hasOne(Game::class, 'collection_item_id'),
            'music' => $this->hasOne(Music::class, 'collection_item_id'),
            'tv_show' => $this->hasOne(TvShow::class, 'collection_item_id'),
            default => $this->hasOne(Movie::class, 'collection_item_id'),
        };
    }
}