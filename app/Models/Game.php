<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Game extends Model
{
    protected $fillable = [
        'collection_item_id',
        'platform',
        'format',
        'genre',
        'publisher',
        'personal_rating',
        'release_year',
        'completed',
        'completion_date',
    ];

    protected $casts = [
        'release_year' => 'integer',
        'completed' => 'boolean',
        'completion_date' => 'date',
    ];

    public function collectionItem(): BelongsTo
    {
        return $this->belongsTo(CollectionItem::class);
    }
}
