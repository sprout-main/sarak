<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $model = trim((string) $request->query('model', ''));
        $action = trim((string) $request->query('action', ''));
        $actorId = $request->query('actor_id');
        $perPage = min(max((int) $request->query('per_page', 25), 5), 100);

        $query = AuditLog::query()->with('actor');

        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('model', 'like', "%{$q}%")
                    ->orWhere('action', 'like', "%{$q}%")
                    ->orWhere('model_id', 'like', "%{$q}%")
                    ->orWhereHas('actor', function ($userQuery) use ($q) {
                        $userQuery->where('name', 'like', "%{$q}%")
                            ->orWhere('email', 'like', "%{$q}%");
                    });

                // Check diff contents if possible
                $sub->orWhere('diff', 'like', "%{$q}%");
            });
        }

        if ($model !== '') {
            $query->where('model', $model);
        }

        if ($action !== '') {
            $query->where('action', $action);
        }

        if ($actorId !== null && $actorId !== '') {
            $query->where('actor_id', (int) $actorId);
        }

        $logs = $query->orderByDesc('id')->paginate($perPage);

        // Fetch distinct models and actions for dropdown filters
        $distinctModels = AuditLog::query()->distinct()->whereNotNull('model')->orderBy('model')->pluck('model');
        $distinctActions = AuditLog::query()->distinct()->whereNotNull('action')->orderBy('action')->pluck('action');

        return response()->json([
            'data' => collect($logs->items())->map(function (AuditLog $log) {
                return [
                    'id' => $log->id,
                    'model' => $log->model,
                    'model_id' => $log->model_id,
                    'action' => $log->action,
                    'actor_id' => $log->actor_id,
                    'actor' => $log->actor ? [
                        'id' => $log->actor->id,
                        'name' => $log->actor->name,
                        'email' => $log->actor->email,
                        'avatar_url' => $log->actor->avatar_url,
                    ] : null,
                    'diff' => $log->diff,
                    'created_at' => $log->created_at?->toISOString(),
                ];
            }),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
            'filters' => [
                'models' => $distinctModels,
                'actions' => $distinctActions,
            ],
        ]);
    }
}
