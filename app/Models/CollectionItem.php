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
        'borrowed_to',
        'due_back_date',
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

    public function tvShow(): HasOne
    {
        return $this->hasOne(TvShow::class, 'collection_item_id');
    }

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