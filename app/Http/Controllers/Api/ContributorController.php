<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\KarmaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContributorController extends Controller
{
    public function __construct(
        private readonly KarmaService $karma,
    ) {
    }

    public function leaderboard(Request $request): JsonResponse
    {
        $limit = $request->integer('limit', 10);
        $data = $this->karma->leaderboard($limit);

        return response()->json([
            'data' => $data,
        ]);
    }
}
