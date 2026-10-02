<?php

namespace App\Services;

use App\Enums\PhotoStatus;
use App\Enums\PlaceStatus;
use App\Enums\QueueAction;
use App\Enums\QueueStatus;
use App\Enums\VerificationTier;
use App\Models\ModQueue;
use App\Models\Place;
use App\Models\PlaceHour;
use App\Models\PlacePhoto;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ModerationService
{
    public function __construct(
        private readonly PlaceService $places,
        private readonly AuditService $audit,
        private readonly PhotoService $photos,
        private readonly KarmaService $karma,
    ) {}

    public function submit(QueueAction $action, array $payload, ?User $actor): ModQueue
    {
        return ModQueue::create([
            'action' => $action->value,
            'place_id' => $payload['place_id'] ?? null,
            'payload' => $payload,
            'requested_by' => $actor?->id,
            'status' => QueueStatus::Pending->value,
        ]);
    }

    public function approve(ModQueue $queue, User $actor): ?Place
    {
        $this->ensurePending($queue);

        $payload = $queue->payload ?? [];

        return DB::transaction(function () use ($queue, $payload, $actor) {
            return match ($queue->action) {
                QueueAction::Create->value => $this->approveCreate($queue, $payload, $actor),
                QueueAction::Update->value => $this->approveUpdate($queue, $payload, $actor),
                QueueAction::Move->value => $this->approveMove($queue, $payload, $actor),
                QueueAction::Photo->value => $this->approvePhoto($queue, $payload, $actor),
                QueueAction::Delete->value => $this->approveDelete($queue, $payload, $actor),
                default => null,
            };
        });
    }

    public function reject(ModQueue $queue, User $actor, string $note): void
    {
        $this->ensurePending($queue);

        $queue->update([
            'status' => QueueStatus::Rejected->value,
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
            'reviewer_note' => $note,
        ]);

        // A rejected photo submission keeps a place_photos row, so leaving it
        // in place made the file unreferenced-but-kept: invisible to the prune
        // command and never collected.
        $rejectedPhoto = $this->queuedPhoto($queue);

        if ($rejectedPhoto !== null) {
            $this->photos->delete($rejectedPhoto);
        }

        $this->audit->record('mod_queue', $queue->id, 'reject', $actor->id, ['note' => $note]);
    }

    /**
     * Permanently removes a queue item and, for a photo submission, the photo
     * it refers to. Deleting the queue row on its own orphaned the upload: the
     * place_photos row survived with nothing left to ever approve it.
     */
    public function discard(ModQueue $queue, User $actor): void
    {
        $this->ensurePending($queue);

        $queuedPhoto = $this->queuedPhoto($queue);

        $queue->delete();

        if ($queuedPhoto !== null) {
            $this->photos->delete($queuedPhoto);
        }

        $this->audit->record('mod_queue', $queue->id, 'discard', $actor->id, [
            'action' => $queue->action,
            'place_id' => $queue->place_id,
            'photo_deleted' => $queuedPhoto !== null,
        ]);
    }

    private function queuedPhoto(ModQueue $queue): ?PlacePhoto
    {
        if ($queue->action !== QueueAction::Photo->value) {
            return null;
        }

        $path = $queue->payload['photo_path'] ?? null;

        if (! is_string($path) || $path === '') {
            return null;
        }

        return PlacePhoto::where('path', $path)->orderBy('id')->first();
    }

    public function merge(ModQueue $queue, Place $target, User $actor): Place
    {
        $this->ensurePending($queue);

        DB::transaction(function () use ($queue, $target, $actor) {
            $source = $queue->place;

            if ($source !== null && $source->id !== $target->id) {
                PlacePhoto::where('place_id', $source->id)->update(['place_id' => $target->id]);
                PlaceHour::where('place_id', $source->id)->update(['place_id' => $target->id]);
                ModQueue::where('place_id', $source->id)
                    ->where('status', QueueStatus::Pending->value)
                    ->update(['place_id' => $target->id]);

                $source->delete();
            }

            $queue->update([
                'status' => QueueStatus::Approved->value,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
            ]);

            $this->audit->record('place', $target->id, 'merge', $actor->id, ['into_place_id' => $target->id]);
        });

        return $target;
    }

    public function diffBeforeAfter(array $payload, ?Place $place): ?array
    {
        if ($place === null) {
            return null;
        }

        $keys = ['category_id', 'name', 'description', 'address', 'barangay_id', 'phone', 'website', 'lat', 'lng'];
        $before = [];
        $after = [];

        foreach ($keys as $key) {
            if (! array_key_exists($key, $payload)) {
                continue;
            }

            $old = $place->{$key};
            $new = $payload[$key];

            if (is_numeric($old) && is_numeric($new)) {
                $old = (float) $old;
                $new = (float) $new;
            }

            if ($old != $new) {
                $before[$key] = $old;
                $after[$key] = $new;
            }
        }

        if (array_key_exists('hours', $payload)) {
            $before['hours_count'] = $place->hours()->count();
            $after['hours_count'] = is_array($payload['hours']) ? count($payload['hours']) : 0;
        }

        if (count($before) === 0) {
            return null;
        }

        return ['before' => $before, 'after' => $after];
    }

    private function approveCreate(ModQueue $queue, array $payload, User $actor): Place
    {
        $place = $this->places->createFromQueue($payload, [
            'status' => PlaceStatus::Approved->value,
            'approved_by' => $actor->id,
            'approved_at' => now(),
            'verified_tier' => VerificationTier::Community->value,
        ]);

        $this->audit->record('place', $place->id, 'create', $actor->id, [
            'name' => $place->name,
            'category_id' => $place->category_id,
            'lat' => $place->lat,
            'lng' => $place->lng,
        ]);

        $this->markReviewed($queue, $actor);

        if ($queue->requester) {
            $this->karma->awardUser($queue->requester, 20);
        }

        return $place;
    }

    private function approveUpdate(ModQueue $queue, array $payload, User $actor): Place
    {
        $place = $this->requirePlace($queue);
        $before = $this->placeSnapshot($place);

        foreach (['category_id', 'name', 'description', 'address', 'barangay_id', 'phone', 'website'] as $field) {
            if (array_key_exists($field, $payload)) {
                $place->{$field} = $payload[$field] ?? null;
            }
        }

        if (isset($payload['lat']) && isset($payload['lng'])) {
            $place->lat = (float) $payload['lat'];
            $place->lng = (float) $payload['lng'];
            $place->geom = $this->places->writeGeometry((float) $payload['lat'], (float) $payload['lng']);

            if (! array_key_exists('barangay_id', $payload)) {
                $place->barangay_id = $this->places->resolveBarangayId((float) $payload['lat'], (float) $payload['lng']);
            }
        }

        if (array_key_exists('hours', $payload)) {
            if (is_array($payload['hours'])) {
                $this->places->syncHours($place, $payload['hours']);
            } else {
                $place->hours()->delete();
            }
        }

        $place->save();

        $after = $this->placeSnapshot($place);
        $this->audit->record('place', $place->id, 'update', $actor->id, ['before' => $before, 'after' => $after]);
        $this->markReviewed($queue, $actor);

        return $place;
    }

    private function approveMove(ModQueue $queue, array $payload, User $actor): Place
    {
        $place = $this->requirePlace($queue);

        $before = [
            'lat' => (float) $place->lat,
            'lng' => (float) $place->lng,
            'barangay_id' => $place->barangay_id,
        ];

        $this->places->applyLocation(
            $place,
            (float) $payload['lat'],
            (float) $payload['lng'],
            $payload['barangay_id'] ?? null,
        );

        $after = [
            'lat' => (float) $place->lat,
            'lng' => (float) $place->lng,
            'barangay_id' => $place->barangay_id,
        ];

        $this->audit->record('place', $place->id, 'move', $actor->id, ['before' => $before, 'after' => $after]);
        $this->markReviewed($queue, $actor);

        return $place;
    }

    private function approvePhoto(ModQueue $queue, array $payload, User $actor): Place
    {
        $path = $payload['photo_path'] ?? null;
        $photo = $path !== null ? PlacePhoto::where('path', $path)->orderBy('id')->first() : null;

        if ($photo === null) {
            throw ValidationException::withMessages(['queue' => 'The referenced photo could not be found.']);
        }

        $photo->update([
            'status' => PhotoStatus::Approved->value,
        ]);

        // Promote only when the contributor asked for the cover or nothing
        // else holds it. Setting it unconditionally flagged every approved
        // photo as a cover and left cover_photo resolving on a tie.
        $this->photos->ensureCover($photo->refresh());

        $place = $photo->place;
        $this->audit->record('place', $place->id, 'photo', $actor->id, ['photo_id' => $photo->id, 'path' => $path]);
        $this->markReviewed($queue, $actor);

        return $place;
    }

    private function approveDelete(ModQueue $queue, array $payload, User $actor): Place
    {
        $place = $this->requirePlace($queue);

        $this->audit->record('place', $place->id, 'delete', $actor->id, [
            'name' => $place->name,
            'category_id' => $place->category_id,
        ]);

        $place->delete();
        $this->markReviewed($queue, $actor);

        return $place;
    }

    private function requirePlace(ModQueue $queue): Place
    {
        $place = $queue->place;

        if ($place === null) {
            throw ValidationException::withMessages(['queue' => 'The queue item has no target place.']);
        }

        return $place;
    }

    private function placeSnapshot(Place $place): array
    {
        return [
            'category_id' => $place->category_id,
            'name' => $place->name,
            'description' => $place->description,
            'address' => $place->address,
            'barangay_id' => $place->barangay_id,
            'phone' => $place->phone,
            'website' => $place->website,
            'lat' => (float) $place->lat,
            'lng' => (float) $place->lng,
            'hours_count' => $place->hours()->count(),
        ];
    }

    private function markReviewed(ModQueue $queue, User $actor): void
    {
        $queue->update([
            'status' => QueueStatus::Approved->value,
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
        ]);
    }

    private function ensurePending(ModQueue $queue): void
    {
        if ($queue->status !== QueueStatus::Pending->value) {
            throw ValidationException::withMessages(['queue' => 'This queue item has already been reviewed.']);
        }
    }
}
