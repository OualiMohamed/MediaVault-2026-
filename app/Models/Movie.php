<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Movie extends Model
{
    // Mass assignable attributes
    protected $fillable = [
        'collection_item_id',
        'format',
        'runtime_minutes',
        'director',
        'genre',
        'personal_rating',
        'release_year',
        'imdb_id',
    ];

    // cast attributes to appropriate data types
    protected $casts = ['release_year' => 'integer'];

    public function collectionItem(): BelongsTo
    {
        return $this->belongsTo(CollectionItem::class, 'collection_item_id');
    }
}
