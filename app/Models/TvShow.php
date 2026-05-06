<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TvShow extends Model
{
    protected $fillable = [
        'collection_item_id',
        'format',
        'seasons',
        'total_seasons',
        'total_episodes',
        'network',
        'genre',
        'personal_rating',
        'release_year',
        'watch_status',
        'current_season',
        'current_episode',
        'trailer_url',
        'actors',
        'director', // Add this line
    ];

    protected $casts = [
        'release_year' => 'integer',
        'seasons' => 'array',
        'actors' => 'array', // Add this line
    ];

    public function collectionItem(): BelongsTo
    {
        return $this->belongsTo(CollectionItem::class, 'collection_item_id');
    }
}