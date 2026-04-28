<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class CollectionItem extends Model
{
    // Mass assignable attributes 
    protected $fillable = [
        'user_id',
        'type',
        'title',
        'cover_image',
        'purchase_date',
        'purchase_price',
        'condition',
        'status',
        'notes',
    ];

    // Cast attributes to appropriate data types
    protected $casts = [
        'purchase_date' => 'date',
        'purchase_price' => 'decimal:2',
    ];

    // Define the relationship to the CollectionItemDetail model
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Define a polymorphic relationship to the specific item details based on the type
    public function details(): MorphOne
    {
        return $this->morphOne(
            match($this->type) {
                'movie' => Movie::class,
                'book'  => Book::class,
                'game'  => Game::class,
                'music' => Music::class,
                default => Movie::class,
            },
            'collectionItem'
        );
    }

    // Define specific relationships for each type for easier access
    public function movie(): MorphOne { return $this->morphOne(Movie::class, 'collectionItem'); }
    public function book(): MorphOne  { return $this->morphOne(Book::class, 'collectionItem'); }
    public function game(): MorphOne  { return $this->morphOne(Game::class, 'collectionItem'); }
    public function music(): MorphOne { return $this->morphOne(Music::class, 'collectionItem'); }
}
