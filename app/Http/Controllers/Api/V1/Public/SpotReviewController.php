<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Actions\Tips\RecordReview;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\StoreReviewRequest;
use App\Models\Business;
use App\Models\Spot;
use Illuminate\Http\JsonResponse;

class SpotReviewController extends Controller
{
    /**
     * Leave a rating and/or review for a spot without a tip. It is recorded for the business.
     */
    public function store(StoreReviewRequest $request, Business $business, Spot $spot, RecordReview $recordReview): JsonResponse
    {
        $recordReview->handle($business->owner, $request->validated(), $request->ip(), $business->id, $spot->id);

        return response()->json(['message' => 'Thank you for your review.'], 201);
    }
}
