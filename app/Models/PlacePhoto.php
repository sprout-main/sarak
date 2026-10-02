<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlacePhoto extends Model
{
    protected $fillable = [
        'place_id',
        'path',
        'uploader_id',
        'is_cover',
        'status',
    ];

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploader_id');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }
}