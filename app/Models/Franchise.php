<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Franchise extends Model
{
    protected $fillable = ['user_id', 'name', 'cover_image'];

    public function movies()
    {
        return $this->hasMany(Movie::class, 'franchise_id')->orderBy('franchise_position');
    }

    public function books()
    {
        return $this->hasMany(Book::class, 'franchise_id')->orderBy('franchise_position');
    }

    public function games()
    {
        return $this->hasMany(Game::class, 'franchise_id')->orderBy('franchise_position');
    }

    public function tvShows()
    {
        return $this->hasMany(TvShow::class, 'franchise_id')->orderBy('franchise_position');
    }

    public function music()
    {
        return $this->hasMany(Music::class, 'franchise_id')->orderBy('franchise_position');
    }

    public function allItems()
    {
        $items = collect();

        foreach ($this->movies as $m) {
            $items->push([...$m->collectionItem->toArray(), 'detail' => $m, 'media_type' => 'movie']);
        }
        foreach ($this->books as $b) {
            $items->push([...$b->collectionItem->toArray(), 'detail' => $b, 'media_type' => 'book']);
        }
        foreach ($this->games as $g) {
            $items->push([...$g->collectionItem->toArray(), 'detail' => $g, 'media_type' => 'game']);
        }
        foreach ($this->tvShows as $t) {
            $items->push([...$t->collectionItem->toArray(), 'detail' => $t, 'media_type' => 'tv_show']);
        }
        foreach ($this->music as $mu) {
            $items->push([...$mu->collectionItem->toArray(), 'detail' => $mu, 'media_type' => 'music']);
        }

        return $items->sortBy('franchise_position')->values();
    }
}