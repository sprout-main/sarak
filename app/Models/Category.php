<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'label',
        'icon_key',
        'color',
        'sort_order',
        'is_active',
    ];

    public function places(): HasMany
    {
        return $this->hasMany(Place::class);
    }

    public function scopeSorted(Builder $query): Builder
    {
        return $query->orderBy('sort_order');
    }

    public function approvedCount(): int
    {
        return $this->places()->where('status', 'approved')->count();
    }
}