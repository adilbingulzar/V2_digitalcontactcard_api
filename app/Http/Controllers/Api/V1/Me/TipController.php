<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Me\ReportPeriodRequest;
use App\Http\Resources\OwnTipResource;
use App\Models\Tip;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TipController extends Controller
{
    /**
     * The signed-in user's tips, ratings and reviews, newest first, with totals
     * of the paid tips in the filtered period.
     */
    public function index(ReportPeriodRequest $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();
        $query = self::filtered($user, $request);

        $totals = (clone $query)
            ->where('payment_status', PaymentStatus::Paid)
            ->selectRaw('COALESCE(SUM(amount), 0) as amount, COUNT(*) as count')
            ->first();

        return OwnTipResource::collection(
            $query->with('business:id,name', 'spot:id,name')->latest('id')->paginate(25)->withQueryString(),
        )->additional(['summary' => [
            'paid_amount' => number_format((float) $totals->amount, 2, '.', ''),
            'paid_count' => (int) $totals->count,
        ]]);
    }

    /**
     * The user's own tips matching the report filters. A tip counts in a
     * period by the time it was paid, or for a review by when it was left.
     *
     * @return Builder<Tip>
     */
    public static function filtered(User $user, ReportPeriodRequest $request): Builder
    {
        $query = Tip::query()->whereBelongsTo($user, 'recipient');

        // Without a status filter, abandoned and failed checkouts are left out.
        if ($request->filled('status')) {
            $query->where('payment_status', $request->enum('status', PaymentStatus::class));
        } else {
            $query->countsAsFeedback();
        }

        if ($range = $request->range()) {
            $query->whereRaw('COALESCE(paid_at, created_at) >= ? AND COALESCE(paid_at, created_at) < ?', $range);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->string('type'));
        }

        return $query;
    }
}
