<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\QueueAction;
use App\Enums\QueueStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\MergeQueueRequest;
use App\Http\Requests\RejectQueueRequest;
use App\Models\ModQueue;
use App\Models\Place;
use App\Services\ModerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QueueController extends Controller
{
    public function __construct(
        private readonly ModerationService $moderation,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $status = $request->query('status');

        $allowed = array_map(fn ($case) => $case->value, QueueStatus::cases());

        if (! is_string($status) || ! in_array($status, $allowed, true)) {
            $status = QueueStatus::Pending->value;
        }

        $items = ModQueue::query()
            ->with(['place.category', 'requester'])
            ->where('status', $status)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'data' => $items->map(fn (ModQueue $item) => [
                'id' => $item->id,
                'action' => $item->action,
                'status' => $item->status,
                'created_at' => $item->created_at?->toISOString(),
                'requested_by' => $item->requester ? [
                    'id' => $item->requester->id,
                    'name' => $item->requester->name,
                ] : null,
                'place' => $item->place ? [
                    'id' => $item->place->id,
                    'name' => $item->place->name,
                    'slug' => $item->place->slug,
                    'lat' => $item->place->lat,
                    'lng' => $item->place->lng,
                    'category' => $item->place->category ? [
                        'id' => $item->place->category->id,
                        'slug' => $item->place->category->slug,
                        'label' => $item->place->category->label,
                        'icon_key' => $item->place->category->icon_key,
                        'color' => $item->place->category->color,
                    ] : null,
                ] : null,
                'payload' => $item->payload,
                'payload_parsed' => $item->payload,
                'diff' => $this->moderation->diffBeforeAfter($item->payload ?? [], $item->place),
            ]),
        ]);
    }

    public function approve(ModQueue $queue, Request $request): JsonResponse
    {
        $this->authorize('approve', $queue);

        if ($queue->status !== QueueStatus::Pending->value) {
            return response()->json(['message' => 'This queue item has already been reviewed.'], 422);
        }

        if ($queue->action === QueueAction::Delete->value && ! $request->user()->isAdmin()) {
            return response()->json(['message' => 'Only administrators can approve deletions.'], 422);
        }

        $place = $this->moderation->approve($queue, $request->user());

        return response()->json([
            'data' => [
                'place_id' => $place?->id,
                'message' => 'Approved',
            ],
        ]);
    }

    public function reject(ModQueue $queue, RejectQueueRequest $request): JsonResponse
    {
        $this->authorize('reject', $queue);

        if ($queue->status !== QueueStatus::Pending->value) {
            return response()->json(['message' => 'This queue item has already been reviewed.'], 422);
        }

        $this->moderation->reject($queue, $request->user(), $request->validated()['note']);

        return response()->json(['data' => ['message' => 'Rejected']]);
    }

    public function merge(ModQueue $queue, MergeQueueRequest $request): JsonResponse
    {
        $this->authorize('merge', $queue);

        if ($queue->status !== QueueStatus::Pending->value) {
            return response()->json(['message' => 'This queue item has already been reviewed.'], 422);
        }

        $target = Place::approved()->find($request->validated()['into_place_id']);

        if ($target === null) {
            return response()->json(['message' => 'The target place has not been approved.'], 422);
        }

        $this->moderation->merge($queue, $target, $request->user());

        return response()->json([
            'data' => [
                'place_id' => $target->id,
            ],
        ]);
    }

    public function destroy(ModQueue $queue): JsonResponse
    {
        $this->authorize('delete', $queue);

        // Goes through the service so a photo submission loses its file too.
        // The row delete alone left the upload on disk with nothing pointing
        // at it beyond a queue record that no longer existed.
        $this->moderation->discard($queue, request()->user());

        return response()->json([
            'data' => [
                'message' => 'Queue item deleted successfully',
            ],
        ]);
    }
}
