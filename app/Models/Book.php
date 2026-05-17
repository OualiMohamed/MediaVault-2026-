<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Book extends Model
{
    protected $fillable = [
        'collection_item_id',
        'author',
        'isbn',
        'page_count',
        'publisher',
        'genre',
        'personal_rating',
        'release_year',
        'read',
        'date_finished',
        'series_id',
        'series_position',
        'franchise_id',
        'franchise_position',
        'language',
    ];

    protected $casts = [
        'release_year' => 'integer',
        'read' => 'boolean',
        'date_finished' => 'date',
    ];

    public function collectionItem(): BelongsTo
    {
        return $this->belongsTo(CollectionItem::class, 'collection_item_id');
    }

    public function series()
    {
        return $this->belongsTo(BookSeries::class, 'series_id');
    }

    public function franchise()
    {
        return $this->belongsTo(Franchise::class, 'franchise_id');
    }
}
