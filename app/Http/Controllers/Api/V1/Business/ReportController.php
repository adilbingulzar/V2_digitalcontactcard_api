<?php

namespace App\Http\Controllers\Api\V1\Business;

use App\Http\Requests\Me\ReportPeriodRequest;
use App\Models\Business;
use App\Models\Tip;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

/**
 * Paid tips of the business in a period: in total, per team member and per spot
 * (the old Tip report and Spot report for businesses).
 */
class ReportController extends BusinessController
{
    public function show(ReportPeriodRequest $request): JsonResponse
    {
        $business = $this->business($request);
        $range = $request->range();

        $totals = $this->paidTips($business, $range)
            ->selectRaw('COALESCE(SUM(amount), 0) as amount, COUNT(*) as count')
            ->first();

        return response()->json(['data' => [
            'total' => ['amount' => $this->money($totals->amount), 'count' => (int) $totals->count],
            'per_member' => $this->grouped($business, $range, 'users', 'tips.recipient_id', 'user_id'),
            'per_spot' => $this->grouped($business, $range, 'spots', 'tips.spot_id', 'spot_id'),
        ]]);
    }

    /**
     * @param  array{0: CarbonImmutable, 1: CarbonImmutable}|null  $range
     * @return list<array<string, mixed>>
     */
    private function grouped(Business $business, ?array $range, string $table, string $foreignKey, string $idName): array
    {
        return $this->paidTips($business, $range)
            ->join($table, "{$table}.id", '=', $foreignKey)
            ->groupBy($foreignKey, "{$table}.name")
            ->selectRaw("{$foreignKey} as id, {$table}.name as name, SUM(tips.amount) as amount, COUNT(*) as count")
            ->orderByDesc('amount')
            ->toBase()
            ->get()
            ->map(fn (object $row): array => [
                $idName => (int) $row->id,
                'name' => $row->name,
                'amount' => $this->money($row->amount),
                'count' => (int) $row->count,
            ])
            ->all();
    }

    /**
     * @param  array{0: CarbonImmutable, 1: CarbonImmutable}|null  $range
     * @return Builder<Tip>
     */
    private function paidTips(Business $business, ?array $range): Builder
    {
        return Tip::query()
            ->where('tips.business_id', $business->id)
            ->where('tips.payment_status', 'paid')
            ->when($range, fn (Builder $query) => $query->where('tips.paid_at', '>=', $range[0])->where('tips.paid_at', '<', $range[1]));
    }

    private function money(mixed $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
