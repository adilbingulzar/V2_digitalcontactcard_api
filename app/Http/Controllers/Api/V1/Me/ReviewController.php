<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Http\Controllers\Controller;
use App\Models\Tip;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Written reviews (the old "Service review" page). A business owner sees all
 * reviews of the business, for team members and spots; others see their own.
 * Pinned reviews are shown first on the business card and the review widget.
 */
class ReviewController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'rating' => ['nullable', 'integer', 'between:1,5'],
            'source' => ['nullable', 'in:person,spot'],
        ]);

        $reviews = $this->reviews($request->user())
            ->when($validated['rating'] ?? null, fn (Builder $tips, int $rating) => $tips->where('rating', $rating))
            ->when(($validated['source'] ?? null) === 'spot', fn (Builder $tips) => $tips->whereNotNull('spot_id'))
            ->when(($validated['source'] ?? null) === 'person', fn (Builder $tips) => $tips->whereNull('spot_id'))
            ->with('recipient:id,name', 'spot:id,name')
            ->orderByDesc('is_review_pinned')
            ->latest('id')
            ->paginate(20);

        return response()->json([
            'data' => $reviews->getCollection()->map(fn (Tip $tip): array => [
                'id' => $tip->id,
                'rating' => $tip->rating,
                'review' => $tip->review,
                'name' => $tip->reviewer_name,
                'email' => $tip->reviewer_email,
                'recipient' => $tip->recipient?->name,
                'spot' => $tip->spot?->name,
                'is_pinned' => $tip->is_review_pinned,
                'created_at' => $tip->created_at?->toIso8601String(),
            ])->all(),
            'meta' => [
                'current_page' => $reviews->currentPage(),
                'last_page' => $reviews->lastPage(),
                'per_page' => $reviews->perPage(),
                'total' => $reviews->total(),
                'from' => $reviews->firstItem(),
                'to' => $reviews->lastItem(),
                'is_business' => $request->user()->business !== null,
            ],
        ]);
    }

    public function pin(Request $request, Tip $tip): JsonResponse
    {
        abort_unless($this->reviews($request->user())->whereKey($tip->id)->exists(), 404);
        $validated = $request->validate(['is_pinned' => ['required', 'boolean']]);

        $tip->forceFill(['is_review_pinned' => $validated['is_pinned']])->save();

        return response()->json(['data' => ['id' => $tip->id, 'is_pinned' => $tip->is_review_pinned]]);
    }

    /**
     * @return Builder<Tip>
     */
    private function reviews(User $user): Builder
    {
        $business = $user->business;

        return Tip::query()
            ->countsAsFeedback()
            ->whereNotNull('review')
            ->where('review', '!=', '')
            ->where(fn (Builder $tips) => $business === null
                ? $tips->where('recipient_id', $user->id)
                : $tips->where('business_id', $business->id)->orWhere('recipient_id', $user->id));
    }
}
