<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\CachesJsonResponses;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\AtlasCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    use CachesJsonResponses;

    public function __construct(private readonly AtlasCache $atlas)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $payload = $this->atlas->categories(fn () => [
            'data' => Category::query()
                ->withCount(['places as place_count' => fn ($query) => $query->where('status', 'approved')])
                ->where('is_active', 1)
                ->orderBy('sort_order')
                ->get()
                ->map(fn (Category $category) => [
                    'id' => $category->id,
                    'slug' => $category->slug,
                    'label' => $category->label,
                    'icon_key' => $category->icon_key,
                    'color' => $category->color,
                    'sort_order' => $category->sort_order,
                    'place_count' => $category->place_count,
                ])
                ->values(),
        ]);

        return $this->cachedJson($request, $payload, 300);
    }
}