<?php

namespace App\Http\Controllers\Api;

use App\Enums\PhotoStatus;
use App\Enums\QueueAction;
use App\Enums\QueueStatus;
use App\Enums\Source;
use App\Http\Controllers\Api\Concerns\CachesJsonResponses;
use App\Http\Controllers\Controller;
use App\Http\Requests\PlaceSubmissionRequest;
use App\Http\Requests\StorePhotoRequest;
use App\Http\Resources\PlaceDetailResource;
use App\Http\Resources\PlaceListResource;
use App\Models\ModQueue;
use App\Models\Place;
use App\Models\PlacePhoto;
use App\Services\AtlasCache;
use App\Services\AuditService;
use App\Services\HoursService;
use App\Services\ModerationService;
use App\Services\PhotoService;
use App\Services\PlaceService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlaceController extends Controller
{
    use CachesJsonResponses;

    private const LIST_COLUMNS = [
        'id',
        'name',
        'slug',
        'category_id',
        'address',
        'barangay_id',
        'lat',
        'lng',
        'source',
        'verified_tier',
        'phone',
        'website',
    ];

    public function __construct(
        private readonly HoursService $hours,
        private readonly ModerationService $moderation,
        private readonly AtlasCache $atlas,
        private readonly PlaceService $places,
        private readonly AuditService $audit,
        private readonly PhotoService $photos,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $etagKey = md5($request->getQueryString() ?? '');
        $ifNoneMatch = $request->header('If-None-Match');

        if (is_string($ifNoneMatch) && $ifNoneMatch !== '') {
            $cachedEtag = $this->atlas->getEtag($etagKey);
            if ($cachedEtag !== null && $this->etagMatches($ifNoneMatch, $cachedEtag)) {
                return response()->json([], 304, [
                    'Cache-Control' => 'public, max-age=30',
                    'ETag' => $cachedEtag,
                    'Vary' => 'Accept',
                ])->setContent('');
            }
        }

        $hoursFilter = $this->normalizeHoursFilter($request->query('hours'));
        $perPage = $this->perPage($request);

        $query = Place::approved()
            ->select(self::LIST_COLUMNS)
            ->with([
                'category',
                'barangay',
                'hours',
                'photos' => fn ($q) => $q
                    ->select(['id', 'place_id', 'path', 'is_cover'])
                    ->where('status', 'approved'),
            ])
            ->withCount(['photos as photo_count' => fn ($q) => $q->where('status', 'approved')]);

        $query = $this->applyFilters($query, $request);

        if ($hoursFilter !== null) {
            $this->applyHoursFilter($query, $hoursFilter);
        }

        if (is_numeric($request->query('lat')) && is_numeric($request->query('lng'))) {
            $query->withDistance($request->query('lat'), $request->query('lng'))
                ->orderBy('distance_m', 'asc');
        } else {
            $query->orderBy('name');
        }

        $paginator = $query->paginate($perPage)->withQueryString();

        $totalApproved = $this->atlas->totalApproved(fn () => Place::approved()->count());

        $payload = [
            'data' => $paginator->getCollection()
                ->map(fn (Place $place) => new PlaceListResource($place))
                ->values(),
            'links' => [
                'first' => $paginator->url(1),
                'last' => $paginator->url(max(1, $paginator->lastPage())),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
            'meta' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => max(1, $paginator->lastPage()),
                'total_approved' => $totalApproved,
            ],
        ];

        return $this->cachedJson($request, $payload, 30);
    }

    public function show(Request $request, Place $place): JsonResponse
    {
        abort_unless($place->status === 'approved', 404);

        $place->load([
            'category',
            'barangay',
            'hours',
            'photos' => fn ($q) => $q->where('status', 'approved'),
            'submittedBy',
            'approvedBy',
        ]);
        $place->loadCount(['photos as photo_count' => fn ($q) => $q->where('status', 'approved')]);

        return $this->cachedJson($request, ['data' => new PlaceDetailResource($place)], 60);
    }

    public function store(PlaceSubmissionRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        if ($user && $user->isAdmin()) {
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
                    'place_id' => $place->id,
                    'slug' => $place->slug,
                    'name' => $place->name,
                    'message' => "Place '{$place->name}' created and published successfully",
                ],
            ], 201);
        }

        $payload = [
            'category_id' => $validated['category_id'],
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'address' => $validated['address'] ?? null,
            'barangay_id' => $validated['barangay_id'] ?? null,
            'lat' => $validated['lat'],
            'lng' => $validated['lng'],
            'phone' => $validated['phone'] ?? null,
            'website' => $validated['website'] ?? null,
            'hours' => $validated['hours'] ?? null,
            'source' => Source::Manual->value,
            'submitted_by' => $user->id,
        ];

        $queue = $this->moderation->submit(QueueAction::Create, $payload, $user);

        return response()->json([
            'data' => [
                'queue_id' => $queue->id,
                'message' => 'Submitted for review',
            ],
        ], 201);
    }

    public function storePhoto(Place $place, StorePhotoRequest $request): JsonResponse
    {
        $path = $request->file('photo')->store("photos/{$place->id}", 'public');

        $photo = PlacePhoto::create([
            'place_id' => $place->id,
            'path' => $path,
            'uploader_id' => $request->user()->id,
            'is_cover' => false,
            'status' => PhotoStatus::Pending->value,
        ]);

        // Administrators do the moderating, so their uploads publish straight
        // away instead of queueing behind their own approval.
        if ($request->user()->isAdmin()) {
            $photo->update(['status' => PhotoStatus::Approved->value]);

            if ($request->boolean('as_cover')) {
                $this->photos->setCover($photo);
            } else {
                $this->photos->ensureCover($photo->refresh());
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

        $queue = $this->moderation->submit(QueueAction::Photo, [
            'photo_path' => $path,
            'place_id' => $place->id,
        ], $request->user());

        return response()->json([
            'data' => [
                'queue_id' => $queue->id,
                'photo_id' => $photo->id,
                'message' => 'Photo submitted for review',
            ],
        ], 201);
    }

    /**
     * Contributors may withdraw their own submission while it is still
     * pending or rejected. PlacePhotoPolicy keeps approved photos admin-only.
     */
    public function destroyPhoto(Place $place, PlacePhoto $photo, Request $request): JsonResponse
    {
        $this->authorize('delete', $photo);

        abort_if($photo->place_id !== $place->id, 404);

        $id = $photo->id;
        $path = $photo->path;

        // Withdrawing the photo withdraws the submission that would approve it.
        ModQueue::where('action', QueueAction::Photo->value)
            ->where('status', QueueStatus::Pending->value)
            ->get()
            ->filter(fn (ModQueue $queue) => ($queue->payload['photo_path'] ?? null) === $path)
            ->each->delete();

        $this->photos->delete($photo);

        $this->audit->record('place_photo', $id, 'delete', $request->user()->id, [
            'place_id' => $place->id,
            'path' => $path,
            'by_uploader' => true,
        ]);

        return response()->json([
            'data' => [
                'id' => $id,
                'message' => 'Photo deleted',
            ],
        ]);
    }

    public function updateCoverPhoto(Place $place, PlacePhoto $photo): JsonResponse
    {
        $this->authorize('updateCover', $photo);

        abort_if($photo->place_id !== $place->id, 404);

        $this->photos->setCover($photo);

        return response()->json([
            'data' => [
                'id' => $photo->id,
                'is_cover' => true,
                'message' => 'Cover preference saved. It applies once an administrator approves the photo.',
            ],
        ]);
    }

    private function perPage(Request $request): int
    {
        $perPage = $request->integer('per_page', 30);

        return $perPage >= 10 && $perPage <= 200 ? $perPage : 30;
    }

    private function applyFilters(Builder $query, Request $request): Builder
    {
        $bboxParam = $request->query('bbox') ?? $request->query('in_bounds');
        if ($bboxParam !== null && $bboxParam !== '') {
            $parts = array_map('trim', explode(',', (string) $bboxParam));

            if (count($parts) !== 4 || ! $this->allNumeric($parts)) {
                abort(422, 'Invalid bbox. Expected south,west,north,east.');
            }

            [$south, $west, $north, $east] = array_map('floatval', $parts);
            $query->inBounds($south, $west, $north, $east);
        }

        if ($request->filled('categories')) {
            $slugs = array_values(array_filter(array_map(
                fn ($value) => trim((string) $value),
                explode(',', $request->query('categories')),
            )));

            if ($slugs !== []) {
                $query->inCategories($slugs);
            }
        }

        if ($request->filled('q')) {
            $query->search($request->query('q'));
        }

        if ($request->filled('barangay')) {
            $query->where('barangay_id', $request->query('barangay'));
        }

        if ($request->filled('tier') && in_array($request->query('tier'), ['verified', 'community', 'imported'], true)) {
            $query->byTier($request->query('tier'));
        }

        if ($request->filled('radius')
            && is_numeric($request->query('lat'))
            && is_numeric($request->query('lng'))
            && is_numeric($request->query('radius'))) {
            $query->withinRadius($request->query('lat'), $request->query('lng'), (float) $request->query('radius'));
        }

        return $query;
    }

    private function applyHoursFilter(Builder $query, string $filter): void
    {
        match ($filter) {
            'open_now' => $this->whereOpenNow($query),
            'open_24h' => $this->whereOpen24h($query),
            'unknown' => $query->whereDoesntHave('hours'),
            default => null,
        };
    }

    private function whereOpenNow(Builder $query): void
    {
        $day = (int) now('Asia/Manila')->dayOfWeek;
        $minutes = (int) now('Asia/Manila')->format('G') * 60 + (int) now('Asia/Manila')->format('i');

        $query->whereIn('places.id', function ($sub) use ($day, $minutes) {
            $sub->select('place_id')
                ->distinct()
                ->from('place_hours')
                ->where('place_hours.day', $day)
                ->where('place_hours.is_closed', 0)
                ->whereNotNull('place_hours.opens')
                ->whereNotNull('place_hours.closes')
                ->whereRaw(
                    '(TIME_TO_SEC(place_hours.opens) DIV 60 = TIME_TO_SEC(place_hours.closes) DIV 60'
                    .' OR (TIME_TO_SEC(place_hours.closes) DIV 60 > TIME_TO_SEC(place_hours.opens) DIV 60'
                    .' AND ? >= TIME_TO_SEC(place_hours.opens) DIV 60'
                    .' AND ? < TIME_TO_SEC(place_hours.closes) DIV 60)'
                    .' OR (TIME_TO_SEC(place_hours.closes) DIV 60 < TIME_TO_SEC(place_hours.opens) DIV 60'
                    .' AND (? >= TIME_TO_SEC(place_hours.opens) DIV 60 OR ? < TIME_TO_SEC(place_hours.closes) DIV 60)))',
                    [$minutes, $minutes, $minutes, $minutes]
                );
        });
    }

    private function whereOpen24h(Builder $query): void
    {
        $query->whereIn('places.id', function ($sub) {
            $sub->select('place_id')
                ->distinct()
                ->from('place_hours')
                ->whereNotNull('place_hours.opens')
                ->whereNotNull('place_hours.closes')
                ->whereRaw(
                    '(TIME_TO_SEC(place_hours.opens) DIV 60 = TIME_TO_SEC(place_hours.closes) DIV 60'
                    .' OR (TIME_TO_SEC(place_hours.opens) DIV 60 = 0'
                    .' AND TIME_TO_SEC(place_hours.closes) DIV 60 = 1439))'
                );
        });
    }

    private function normalizeHoursFilter(mixed $value): ?string
    {
        if (is_string($value) && in_array($value, ['open_now', 'open_24h', 'unknown'], true)) {
            return $value;
        }

        return null;
    }

    private function allNumeric(array $values): bool
    {
        foreach ($values as $value) {
            if (! is_numeric($value)) {
                return false;
            }
        }

        return true;
    }
}
