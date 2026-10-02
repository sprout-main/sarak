<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $table = 'audit_log';

    public const UPDATED_AT = null;

    protected $fillable = [
        'model',
        'model_id',
        'actor_id',
        'action',
        'diff',
    ];

    protected function casts(): array
    {
        return [
            'diff' => 'array',
        ];
    }

    public function actor(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}