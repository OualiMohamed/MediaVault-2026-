<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookSeries extends Model
{
    protected $table = 'book_series';

    protected $fillable = ['user_id', 'name'];

    public function books()
    {
        return $this->hasMany(Book::class, 'series_id')->orderBy('series_position');
    }
}