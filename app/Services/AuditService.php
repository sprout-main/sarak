<?php

namespace App\Services;

use App\Models\AuditLog;

class AuditService
{
    public function record(string $model, int|string $modelId, string $action, ?int $actorId = null, ?array $diff = null): AuditLog
    {
        return AuditLog::create([
            'model' => $model,
            'model_id' => $modelId,
            'actor_id' => $actorId,
            'action' => $action,
            'diff' => $diff,
        ]);
    }
}