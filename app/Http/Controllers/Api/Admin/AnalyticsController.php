<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\QueueStatus;
use App\Http\Controllers\Controller;
use App\Models\BoundaryArea;
use App\Models\Category;
use App\Models\ModQueue;
use App\Models\Place;
use Illuminate\Http\JsonResponse;

class AnalyticsController extends Controller
{
    public function coverage(): JsonResponse
    {
        $categories = Category::query()
            ->where('is_active', 1)
            ->orderBy('sort_order')
            ->get();

        $barangays = BoundaryArea::query()
            ->barangays()
            ->orderBy('name')
            ->get(['id', 'name']);

        $matrixKeys = Place::approved()
            ->whereNotNull('barangay_id')
            ->selectRaw('category_id, barangay_id, COUNT(*) AS c')
            ->groupBy('category_id', 'barangay_id')
            ->get()
            ->keyBy(fn ($row) => $row->category_id.'-'.$row->barangay_id);

        $approvedByCategory = Place::approved()
            ->selectRaw('category_id, COUNT(*) AS c')
            ->groupBy('category_id')
            ->pluck('c', 'category_id');

        $pendingByCategory = $this->pendingByCategory($categories);

        $matrix = $categories->map(function (Category $category) use ($barangays, $matrixKeys) {
            return [
                'category' => [
                    'id' => $category->id,
                    'slug' => $category->slug,
                    'label' => $category->label,
                ],
                'barangays' => $barangays->map(function (BoundaryArea $barangay) use ($category, $matrixKeys) {
                    return [
                        'id' => $barangay->id,
                        'name' => $barangay->name,
                        'count' => (int) ($matrixKeys[$category->id.'-'.$barangay->id]->c ?? 0),
                    ];
                })->values(),
            ];
        })->values();

        $totals = $categories->map(fn (Category $category) => [
            'category_id' => $category->id,
            'approved' => (int) ($approvedByCategory[$category->id] ?? 0),
            'pending' => $pendingByCategory[$category->id] ?? 0,
        ]);

        $emptySpots = [];

        foreach ($categories as $category) {
            foreach ($barangays as $barangay) {
                if (! isset($matrixKeys[$category->id.'-'.$barangay->id])) {
                    $emptySpots[] = [
                        'category_id' => $category->id,
                        'barangay_id' => $barangay->id,
                        'barangay_name' => $barangay->name,
                    ];
                }
            }
        }

        return response()->json([
            'matrix' => $matrix,
            'totals' => $totals,
            'empty_spots' => $emptySpots,
        ]);
    }

    public function pending(): JsonResponse
    {
        $groups = ModQueue::query()
            ->where('status', QueueStatus::Pending->value)
            ->selectRaw('action, COUNT(*) AS c')
            ->groupBy('action')
            ->pluck('c', 'action');

        $byAction = [
            'create' => (int) ($groups['create'] ?? 0),
            'update' => (int) ($groups['update'] ?? 0),
            'move' => (int) ($groups['move'] ?? 0),
            'photo' => (int) ($groups['photo'] ?? 0),
            'delete' => (int) ($groups['delete'] ?? 0),
        ];

        return response()->json([
            'total' => array_sum($byAction),
            'by_action' => $byAction,
        ]);
    }

    private function pendingByCategory($categories): array
    {
        $pending = [];

        foreach (ModQueue::query()->where('status', QueueStatus::Pending->value)->with('place')->get() as $queue) {
            $categoryId = $queue->action === 'create'
                ? ($queue->payload['category_id'] ?? null)
                : $queue->place?->category_id;

            if ($categoryId !== null) {
                $pending[(int) $categoryId] = ($pending[(int) $categoryId] ?? 0) + 1;
            }
        }

        return $pending;
    }
}