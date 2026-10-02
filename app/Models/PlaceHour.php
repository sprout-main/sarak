<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlaceHour extends Model
{
    protected $fillable = [
        'place_id',
        'day',
        'opens',
        'closes',
        'is_closed',
        'note',
    ];

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }

    public function scopeForDay($query, int $day)
    {
        return $query->where('day', $day);
    }
}