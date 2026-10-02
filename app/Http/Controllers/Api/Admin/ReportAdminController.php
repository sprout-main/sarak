<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlaceReport;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReportAdminController extends Controller
{
    public function __construct(
        private readonly AuditService $audit,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $status = $request->query('status', 'pending');
        $q = trim((string) $request->query('q', ''));
        $perPage = min(max((int) $request->query('per_page', 25), 5), 100);

        $query = PlaceReport::query()->with(['place.category', 'user', 'reviewer']);

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('details', 'like', "%{$q}%")
                    ->orWhere('issue_type', 'like', "%{$q}%")
                    ->orWhereHas('place', fn ($p) => $p->where('name', 'like', "%{$q}%"));
            });
        }

        $reports = $query->orderByDesc('id')->paginate($perPage);

        return response()->json([
            'data' => collect($reports->items())->map(fn (PlaceReport $r) => [
                'id' => $r->id,
                'issue_type' => $r->issue_type,
                'details' => $r->details,
                'status' => $r->status,
                'created_at' => $r->created_at?->toISOString(),
                'reviewed_at' => $r->reviewed_at?->toISOString(),
                'admin_notes' => $r->admin_notes,
                'place' => $r->place ? [
                    'id' => $r->place->id,
                    'name' => $r->place->name,
                    'slug' => $r->place->slug,
                    'address' => $r->place->address,
                    'lat' => $r->place->lat,
                    'lng' => $r->place->lng,
                    'category' => $r->place->category ? [
                        'id' => $r->place->category->id,
                        'label' => $r->place->category->label,
                        'color' => $r->place->category->color,
                        'icon_key' => $r->place->category->icon_key,
                    ] : null,
                ] : null,
                'reporter' => $r->user ? [
                    'id' => $r->user->id,
                    'name' => $r->user->name,
                    'email' => $r->user->email,
                ] : null,
                'reviewer' => $r->reviewer ? [
                    'id' => $r->reviewer->id,
                    'name' => $r->reviewer->name,
                ] : null,
            ]),
            'meta' => [
                'current_page' => $reports->currentPage(),
                'last_page' => $reports->lastPage(),
                'per_page' => $reports->perPage(),
                'total' => $reports->total(),
            ],
            'counts' => [
                'pending' => PlaceReport::where('status', 'pending')->count(),
                'resolved' => PlaceReport::where('status', 'resolved')->count(),
                'dismissed' => PlaceReport::where('status', 'dismissed')->count(),
                'total' => PlaceReport::count(),
            ],
        ]);
    }

    public function updateStatus(PlaceReport $report, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['pending', 'resolved', 'dismissed'])],
            'admin_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $report->status = $validated['status'];
        if (isset($validated['admin_notes'])) {
            $report->admin_notes = $validated['admin_notes'];
        }
        $report->reviewed_by = $request->user()->id;
        $report->reviewed_at = now();
        $report->save();

        $this->audit->record('place_report', $report->id, 'status_update', $request->user()->id, [
            'status' => $report->status,
            'admin_notes' => $report->admin_notes,
        ]);

        return response()->json([
            'data' => [
                'id' => $report->id,
                'status' => $report->status,
                'admin_notes' => $report->admin_notes,
                'reviewed_at' => $report->reviewed_at->toISOString(),
                'message' => 'Report status updated successfully.',
            ],
        ]);
    }

    public function destroy(PlaceReport $report, Request $request): JsonResponse
    {
        $id = $report->id;
        $report->delete();

        $this->audit->record('place_report', $id, 'delete', $request->user()?->id);

        return response()->json([
            'data' => [
                'message' => 'Report deleted successfully.',
            ],
        ]);
    }
}
