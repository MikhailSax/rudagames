<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    public function categories(): HasMany
    {
        return $this->hasMany(GameCategory::class);
    }

    public function games(): HasMany
    {
        return $this->hasMany(Game::class);
    }

    public function teamsWhoFavorIt(): HasMany
    {
        return $this->hasMany(Team::class, 'favorite_product_id');
    }
}
