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
        'trailer_url',
        'watch_status',      // was: 'seen'
        'date_seen',
        'video_quality',
        'audio_format',
        'file_size',         // add this
        'language',
        'actors',
        'franchise_id',
        'franchise_position',
        'video_tier',
        'original_title',    // add this
    ];

    // cast attributes to appropriate data types
    protected $casts = ['release_year' => 'integer', 'actors' => 'array', 'audio_format' => 'array',];

    public function collectionItem(): BelongsTo
    {
        return $this->belongsTo(CollectionItem::class, 'collection_item_id');
    }

    public function franchise()
    {
        return $this->belongsTo(Franchise::class, 'franchise_id');
    }
}
