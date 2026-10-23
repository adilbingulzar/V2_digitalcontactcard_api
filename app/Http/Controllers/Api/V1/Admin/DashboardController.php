<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\AccountType;
use App\Enums\PaymentStatus;
use App\Enums\WithdrawalStatus;
use App\Models\FeeCollection;
use App\Models\Subscription;
use App\Models\Tip;
use App\Models\User;
use App\Models\VisitorLog;
use App\Models\Withdrawal;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

class DashboardController extends AdminController
{
    /**
     * Platform totals for the admin start page.
     */
    public function show(): JsonResponse
    {
        $monthStart = CarbonImmutable::now(config('tiqs.business_timezone'))->startOfMonth()->utc();
        $paidTips = fn () => Tip::query()->paid()->where('amount', '>', 0);
        $paidFees = fn () => FeeCollection::query()->where('payment_status', PaymentStatus::Paid);
        $openPayouts = Withdrawal::query()->where('status', WithdrawalStatus::Requested);

        return response()->json(['data' => [
            'users' => [
                'total' => User::query()->count(),
                'business' => User::query()->where('account_type', AccountType::Business)->count(),
                'new_this_month' => User::query()->where('created_at', '>=', $monthStart)->count(),
            ],
            'tips' => [
                'total' => $this->money($paidTips()->sum('amount')),
                'count' => $paidTips()->count(),
                'this_month' => $this->money($paidTips()->where('paid_at', '>=', $monthStart)->sum('amount')),
            ],
            'platform_fees' => [
                'total' => $this->money($paidFees()->sum('fee_amount')),
                'this_month' => $this->money($paidFees()->where('created_at', '>=', $monthStart)->sum('fee_amount')),
            ],
            'payouts' => [
                'open_count' => (clone $openPayouts)->count(),
                'open_amount' => $this->money((clone $openPayouts)->sum('amount')),
                'paid_this_month' => $this->money(Withdrawal::query()->where('status', WithdrawalStatus::Paid)->where('paid_at', '>=', $monthStart)->sum('amount')),
                'fees_total' => $this->money(Withdrawal::query()->where('status', WithdrawalStatus::Paid)->sum('fee')),
            ],
            'subscriptions_active' => Subscription::query()->where('is_active', true)->whereDate('ends_on', '>=', today())->count(),
            'visits_last_30_days' => VisitorLog::query()->where('created_at', '>=', now()->subDays(30))->count(),
        ]]);
    }
}
