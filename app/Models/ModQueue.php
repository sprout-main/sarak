<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModQueue extends Model
{
    protected $table = 'mod_queue';

    protected $fillable = [
        'place_id',
        'action',
        'payload',
        'requested_by',
        'status',
        'reviewed_by',
        'reviewed_at',
        'reviewer_note',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}