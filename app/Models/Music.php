<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Music extends Model
{
    protected $fillable = [
        'collection_item_id',
        'format',
        'artist',
        'genre',
        'label',
        'track_count',
        'personal_rating',
        'release_year',
        'vinyl_speed',
        'tracks',
    ];

    protected $casts = ['release_year' => 'integer', 'tracks' => 'array'];

    public function collectionItem(): BelongsTo
    {
        return $this->belongsTo(CollectionItem::class, 'collection_item_id');
    }
}
