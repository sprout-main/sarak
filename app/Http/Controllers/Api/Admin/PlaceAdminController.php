<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\PhotoStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\PlaceSubmissionRequest;
use App\Http\Requests\StorePhotoRequest;
use App\Http\Requests\UpdateLocationRequest;
use App\Http\Requests\UpdatePlaceRequest;
use App\Models\Place;
use App\Models\PlacePhoto;
use App\Services\AtlasCache;
use App\Services\AuditService;
use App\Services\PhotoService;
use App\Services\PlaceService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PlaceAdminController extends Controller
{
    public function __construct(
        private readonly PlaceService $places,
        private readonly AuditService $audit,
        private readonly AtlasCache $atlas,
        private readonly PhotoService $photos,
    ) {}

    public function store(PlaceSubmissionRequest $request): JsonResponse
    {
        $this->authorize('create', Place::class);

        $validated = $request->validated();
        $user = $request->user();

        $place = $this->places->createDirect($validated, $user);

        $this->atlas->invalidate();

        $this->audit->record('place', $place->id, 'create', $user->id, [
            'name' => $place->name,
            'category_id' => $place->category_id,
            'lat' => $place->lat,
            'lng' => $place->lng,
            'barangay_id' => $place->barangay_id,
            'direct_admin_creation' => true,
        ]);

        return response()->json([
            'data' => [
                'id' => $place->id,
                'name' => $place->name,
                'slug' => $place->slug,
                'message' => "Place '{$place->name}' created and published successfully",
            ],
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $query = Place::query()
            ->with(['category', 'barangay'])
            ->when($request->filled('q'), fn (Builder $q) => $q->search($request->query('q')))
            ->when($request->filled('category_id'), fn (Builder $q) => $q->where('category_id', $request->query('category_id')))
            ->when($request->filled('status'), fn (Builder $q) => $q->where('status', $request->query('status')))
            ->orderByDesc('updated_at');

        $page = $query->paginate(50);

        return response()->json([
            'data' => collect($page->items())->map(fn (Place $place) => [
                'id' => $place->id,
                'name' => $place->name,
                'slug' => $place->slug,
                'category' => $place->category ? [
                    'id' => $place->category->id,
                    'slug' => $place->category->slug,
                    'label' => $place->category->label,
                    'icon_key' => $place->category->icon_key,
                    'color' => $place->category->color,
                ] : null,
                'barangay' => $place->barangay ? [
                    'id' => $place->barangay->id,
                    'name' => $place->barangay->name,
                ] : null,
                'lat' => $place->lat,
                'lng' => $place->lng,
                'status' => $place->status,
                'source' => $place->source,
                'verified_tier' => $place->verified_tier,
                'updated_at' => $place->updated_at?->toISOString(),
            ]),
            'meta' => [
                'total' => $page->total(),
                'per_page' => $page->perPage(),
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }

    public function update(Place $place, UpdatePlaceRequest $request): JsonResponse
    {
        $this->authorize('update', $place);

        $validated = $request->validated();
        $before = $this->snapshot($place);

        DB::transaction(function () use ($place, $validated, $request) {
            $wasPending = $place->status === 'pending';
            foreach (['name', 'description', 'address', 'phone', 'website', 'status'] as $field) {
                if (array_key_exists($field, $validated)) {
                    $place->{$field} = $validated[$field] ?? null;
                }
            }

            if (array_key_exists('category_id', $validated)) {
                $place->category_id = $validated['category_id'] ?? null;
            }

            if ($request->has('hours')) {
                $hours = $validated['hours'];

                if (is_array($hours)) {
                    $this->places->syncHours($place, $hours);
                } else {
                    $place->hours()->delete();
                }
            }

            $place->save();

            if ($wasPending && $place->status === 'approved' && $place->submittedBy) {
                app(\App\Services\KarmaService::class)->awardUser($place->submittedBy, 20);
            }
        });

        $after = $this->snapshot($place);

        $diff = [];
        foreach ($after as $key => $value) {
            if (($before[$key] ?? null) != $value) {
                $diff[$key] = ['before' => $before[$key] ?? null, 'after' => $value];
            }
        }

        $this->audit->record('place', $place->id, 'update', $request->user()->id, $diff);

        $this->atlas->invalidate();

        return response()->json([
            'data' => [
                'id' => $place->id,
                'message' => 'Place updated',
            ],
        ]);
    }

    public function updateLocation(Place $place, UpdateLocationRequest $request): JsonResponse
    {
        $this->authorize('updateLocation', $place);

        $validated = $request->validated();
        $before = [
            'lat' => (float) $place->lat,
            'lng' => (float) $place->lng,
            'barangay_id' => $place->barangay_id,
        ];

        $this->places->applyLocation(
            $place,
            (float) $validated['lat'],
            (float) $validated['lng'],
            $validated['barangay_id'] ?? null,
        );

        $after = [
            'lat' => (float) $place->lat,
            'lng' => (float) $place->lng,
            'barangay_id' => $place->barangay_id,
        ];

        $this->audit->record('place', $place->id, 'move', $request->user()->id, [
            'before' => $before,
            'after' => $after,
        ]);

        return response()->json([
            'data' => [
                'id' => $place->id,
                'message' => 'Location updated',
            ],
        ]);
    }

    public function massRelocate(Request $request): JsonResponse
    {
        abort_unless($request->user()?->isHeadAdmin(), 403, 'Only the head administrator may mass relocate places.');

        $validated = $request->validate([
            'moves' => ['required', 'array', 'min:1', 'max:200'],
            'moves.*.id' => ['required', 'integer', 'exists:places,id'],
            'moves.*.lat' => ['required', 'numeric', 'between:-90,90'],
            'moves.*.lng' => ['required', 'numeric', 'between:-180,180'],
            'moves.*.barangay_id' => ['nullable', 'integer', 'exists:boundary_areas,id'],
        ]);

        $updatedPlaces = DB::transaction(function () use ($validated, $request) {
            $updated = [];
            foreach ($validated['moves'] as $move) {
                /** @var Place $place */
                $place = Place::findOrFail($move['id']);
                $this->authorize('updateLocation', $place);

                $before = [
                    'lat' => (float) $place->lat,
                    'lng' => (float) $place->lng,
                    'barangay_id' => $place->barangay_id,
                ];

                $this->places->applyLocation(
                    $place,
                    (float) $move['lat'],
                    (float) $move['lng'],
                    $move['barangay_id'] ?? null,
                );

                $place->refresh();

                $after = [
                    'lat' => (float) $place->lat,
                    'lng' => (float) $place->lng,
                    'barangay_id' => $place->barangay_id,
                ];

                $this->audit->record('place', $place->id, 'mass_move', $request->user()->id, [
                    'before' => $before,
                    'after' => $after,
                ]);

                $updated[] = [
                    'id' => $place->id,
                    'name' => $place->name,
                    'lat' => (float) $place->lat,
                    'lng' => (float) $place->lng,
                    'barangay_id' => $place->barangay_id,
                ];
            }

            return $updated;
        });

        $this->atlas->invalidate();

        return response()->json([
            'data' => [
                'updated_count' => count($updatedPlaces),
                'places' => $updatedPlaces,
                'message' => 'Successfully relocated '.count($updatedPlaces).' '.(count($updatedPlaces) === 1 ? 'place' : 'places').'.',
            ],
        ]);
    }

    public function destroy(Place $place, Request $request): JsonResponse
    {
        $this->authorize('delete', $place);

        $id = $place->id;
        $name = $place->name;
        $category = $place->category?->label;

        // Photos first: this drops the rows and unlinks the files, so the
        // cascade cannot take the rows and leave the files behind.
        DB::transaction(function () use ($place) {
            $place->hours()->delete();
        });

        $this->photos->deleteForPlace($id);
        $place->forceDelete();

        $this->audit->record('place', $id, 'delete', $request->user()->id, [
            'name' => $name,
            'category' => $category,
        ]);

        return response()->json([
            'data' => [
                'id' => $id,
                'message' => "Place '{$name}' deleted successfully",
            ],
        ]);
    }

    /**
     * Administrator uploads publish immediately. Admin place creation already
     * goes direct, so queueing admin photos only meant an admin had to approve
     * their own work before it appeared.
     */
    public function storePhoto(Place $place, StorePhotoRequest $request): JsonResponse
    {
        $path = $request->file('photo')->store("photos/{$place->id}", 'public');

        $photo = PlacePhoto::create([
            'place_id' => $place->id,
            'path' => $path,
            'uploader_id' => $request->user()->id,
            'is_cover' => false,
            'status' => PhotoStatus::Approved->value,
        ]);

        if ($request->boolean('as_cover')) {
            $this->photos->setCover($photo);
        } else {
            $this->photos->ensureCover($photo);
        }

        $this->atlas->invalidate();

        $this->audit->record('place', $place->id, 'photo', $request->user()->id, [
            'photo_id' => $photo->id,
            'path' => $path,
            'direct_admin_upload' => true,
        ]);

        return response()->json([
            'data' => [
                'photo_id' => $photo->id,
                'place_id' => $place->id,
                'path' => $path,
                'status' => $photo->status,
                'is_cover' => (bool) $photo->is_cover,
                'message' => 'Photo uploaded and published',
            ],
        ], 201);
    }

    public function destroyPhoto(Place $place, PlacePhoto $photo, Request $request): JsonResponse
    {
        $this->authorize('delete', $photo);

        abort_if($photo->place_id !== $place->id, 404);

        $id = $photo->id;
        $path = $photo->path;
        $wasCover = (bool) $photo->is_cover;

        $this->photos->delete($photo);

        // Deleting the cover would leave the place with no cover at all.
        if ($wasCover) {
            $this->photos->ensureCover(
                PlacePhoto::where('place_id', $place->id)->orderBy('id')->first()
            );
        }

        $this->audit->record('place_photo', $id, 'delete', $request->user()->id, [
            'place_id' => $place->id,
            'path' => $path,
            'was_cover' => $wasCover,
        ]);

        return response()->json([
            'data' => [
                'id' => $id,
                'message' => 'Photo deleted',
            ],
        ]);
    }

    public function updateCoverPhoto(Place $place, PlacePhoto $photo, Request $request): JsonResponse
    {
        $this->authorize('updateCover', $photo);

        abort_if($photo->place_id !== $place->id, 404);

        $this->photos->setCover($photo);

        $this->audit->record('place_photo', $photo->id, 'set_cover', $request->user()->id, [
            'place_id' => $place->id,
            'path' => $photo->path,
        ]);

        return response()->json([
            'data' => [
                'id' => $photo->id,
                'is_cover' => true,
                'message' => 'Cover photo updated',
            ],
        ]);
    }

    public function indexPhotos(Place $place): JsonResponse
    {
        $this->authorize('update', $place);

        return response()->json([
            'data' => $place->photos()->get()->map(fn (PlacePhoto $photo) => [
                'id' => $photo->id,
                'place_id' => $photo->place_id,
                'path' => $photo->path,
                'status' => $photo->status,
                'is_cover' => (bool) $photo->is_cover,
                'uploader_id' => $photo->uploader_id,
                'created_at' => $photo->created_at?->toISOString(),
            ]),
        ]);
    }

    private function snapshot(Place $place): array
    {
        return [
            'category_id' => $place->category_id,
            'name' => $place->name,
            'description' => $place->description,
            'address' => $place->address,
            'phone' => $place->phone,
            'website' => $place->website,
            'hours_count' => $place->hours()->count(),
        ];
    }
}
