<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Place;
use App\Services\KarmaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClaimController extends Controller
{
    public function __construct(
        private readonly KarmaService $karma,
    ) {
    }

    public function claim(Request $request, Place $place): JsonResponse
    {
        abort_unless($place->status === 'approved', 404);

        if ($place->is_claimed) {
            abort(422, 'Place is already claimed by a verified owner.');
        }

        $user = $request->user();

        $place->is_claimed = true;
        $place->claimed_by = $user->id;
        $place->save();

        $this->karma->awardUser($user, 20);

        return response()->json([
            'data' => [
                'message' => 'Listing claimed successfully.',
                'place_id' => $place->id,
                'is_claimed' => true,
                'karma_earned' => 20,
            ],
        ]);
    }
}
