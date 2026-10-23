<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\PaymentStatus;
use App\Models\FeeCollection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Platform fees taken from paid tips (the old "Fees collections" page).
 */
class FeeController extends AdminController
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'until' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $query = FeeCollection::query()
            ->where('payment_status', PaymentStatus::Paid)
            ->when($validated['from'] ?? null, fn (Builder $fees, string $from) => $fees->whereDate('created_at', '>=', $from))
            ->when($validated['until'] ?? null, fn (Builder $fees, string $until) => $fees->whereDate('created_at', '<=', $until));

        $totals = (clone $query)->selectRaw('COALESCE(SUM(tip_amount), 0) as tips, COALESCE(SUM(fee_amount), 0) as fees, COUNT(*) as count')->first();
        $page = $query->with('user:id,name,email', 'tip:id,spot_id,business_id', 'tip.spot:id,name', 'tip.business:id,name')->latest('id')->paginate(50)->withQueryString();

        return $this->paginated($page, fn (FeeCollection $fee): array => [
            'id' => $fee->id,
            'user' => $fee->user?->only(['id', 'name', 'email']),
            'business' => $fee->tip?->business?->name,
            'spot' => $fee->tip?->spot?->name,
            'paid_by' => $fee->fee_type === 2 ? 'customer' : 'recipient',
            'tip_amount' => $fee->tip_amount,
            'fee_amount' => $fee->fee_amount,
            'created_at' => $fee->created_at?->toIso8601String(),
        ], ['totals' => ['tips' => $this->money($totals->tips), 'fees' => $this->money($totals->fees), 'count' => (int) $totals->count]]);
    }
}
