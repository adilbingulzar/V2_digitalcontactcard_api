<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Actions\Tips\RecordReview;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\StoreReviewRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class ProfileReviewController extends Controller
{
    /**
     * Leave a rating and/or review without a tip.
     */
    public function store(StoreReviewRequest $request, User $user, RecordReview $recordReview): JsonResponse
    {
        $recordReview->handle($user, $request->validated(), $request->ip());

        return response()->json(['message' => 'Thank you for your review.'], 201);
    }
}
