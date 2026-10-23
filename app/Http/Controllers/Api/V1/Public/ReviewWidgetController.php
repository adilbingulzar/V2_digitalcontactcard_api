<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Tip;
use App\Models\WidgetSetting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

/**
 * The review widget businesses embed on their own website (old URL
 * /widgets/review/{business id}): the business, its rating and its written
 * reviews, pinned ones first.
 */
class ReviewWidgetController extends Controller
{
    public function show(Business $business): JsonResponse
    {
        abort_unless($business->is_active, 404);

        $owner = $business->owner;
        $settings = WidgetSetting::query()->whereBelongsTo($business)->where('widget', 'review')->first();
        $feedback = Tip::query()
            ->countsAsFeedback()
            ->where(fn (Builder $tips) => $tips->where('business_id', $business->id)->when($owner !== null, fn (Builder $mine) => $mine->orWhere('recipient_id', $owner->id)));
        $ratings = (clone $feedback)->whereNotNull('rating')->where('rating', '>', 0);

        $reviews = (clone $feedback)
            ->whereNotNull('review')
            ->where('review', '!=', '')
            ->orderByDesc('is_review_pinned')
            ->latest('id')
            ->paginate(10);

        return response()->json([
            'data' => [
                'business' => [
                    'name' => $business->name,
                    'address' => $business->address,
                    'logo' => $owner?->avatarUrl(),
                    'review_url' => $owner === null ? null : rtrim((string) config('tiqs.frontend_url'), '/').'/review/'.$owner->slug,
                ],
                'rating' => [
                    'average' => round((float) (clone $ratings)->avg('rating'), 1),
                    'count' => (clone $ratings)->count(),
                ],
                'theme' => $settings?->theme === 1 ? 'dark' : 'light',
                'reviews' => collect($reviews->items())->map(fn (Tip $tip): array => [
                    'id' => $tip->id,
                    'rating' => $tip->rating,
                    'review' => $tip->review,
                    'name' => $tip->reviewer_name,
                    'date' => $tip->created_at?->toDateString(),
                ])->all(),
            ],
            'meta' => ['current_page' => $reviews->currentPage(), 'last_page' => $reviews->lastPage(), 'total' => $reviews->total()],
        ]);
    }
}
