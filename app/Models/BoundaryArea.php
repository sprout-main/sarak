<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BoundaryArea extends Model
{
    protected $fillable = [
        'name',
        'type',
        'geom',
        'boundary_source',
        'psgc',
        'imported_at',
    ];

    protected $casts = [
        'imported_at' => 'datetime',
    ];

    public function places(): HasMany
    {
        return $this->hasMany(Place::class, 'barangay_id');
    }

    public function scopeBarangays($query)
    {
        return $query->where('type', 'barangay');
    }
}