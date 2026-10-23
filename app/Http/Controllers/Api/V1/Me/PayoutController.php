<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Actions\Payouts\CalculatePayout;
use App\Actions\Payouts\RequestPayout;
use App\Actions\Subscriptions\SubscriptionStatus;
use App\Enums\WithdrawalStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\WithdrawalResource;
use App\Models\Referral;
use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PayoutController extends Controller
{
    /**
     * What the user can be paid out now, on what schedule, and to which account.
     */
    public function show(Request $request, CalculatePayout $calculate, SubscriptionStatus $subscriptionStatus): JsonResponse
    {
        $user = $request->user();
        $payout = $calculate->handle($user);
        $bankAccount = $user->bankAccount;
        $subscription = $subscriptionStatus->for($user);

        $reason = match (true) {
            $bankAccount === null => 'Add your bank account first.',
            ! $subscription['allows_payout'] => 'Choose a plan to transfer your balance.',
            $payout['net_cents'] < $payout['minimum_cents'] => 'Your balance is below the minimum payout.',
            default => null,
        };

        $euros = fn (int $cents): string => number_format($cents / 100, 2, '.', '');

        return response()->json(['data' => [
            'balance' => $euros($payout['balance_cents']),
            'fee' => $euros($payout['fee_cents']),
            'net' => $euros($payout['net_cents']),
            'minimum' => $euros($payout['minimum_cents']),
            'can_request' => $reason === null,
            'subscription' => $subscription,
            'reason' => $reason,
            'settings' => [
                'schedule' => $payout['schedule'],
                'platform_fee_paid_by' => UserSetting::query()->whereBelongsTo($user)->where('key', 'tax_deduction')->value('value') === '1' ? 'customer' : 'recipient',
            ],
            'fees' => config('tiqs.payouts.fees'),
            'bank_account' => BankAccountController::present($bankAccount),
            // The old page split the balance and listed recent transfers and rewards.
            'breakdown' => [
                'tips' => $this->money($user->receivedTips()->availableForPayout()->whereNull('spot_id')->sum('amount')),
                'spots' => $this->money($user->receivedTips()->availableForPayout()->whereNotNull('spot_id')->sum('amount')),
                'referrals' => $this->money($this->unpaidReferrals($user)->sum('amount')),
            ],
            'processing' => $this->money($user->withdrawals()->where('status', WithdrawalStatus::Requested)->sum('amount')),
            'recent_withdrawals' => WithdrawalResource::collection($user->withdrawals()->latest('id')->limit(5)->get()),
            'recent_referrals' => Referral::query()
                ->where('referrer_id', $user->id)
                ->where('is_confirmed', true)
                ->latest('id')
                ->limit(5)
                ->get(['id', 'amount', 'created_at'])
                ->map(fn (Referral $referral): array => ['id' => $referral->id, 'amount' => $this->money($referral->amount), 'created_at' => $referral->created_at?->toIso8601String()]),
        ]]);
    }

    /**
     * Request a payout of the whole balance.
     */
    /**
     * @return Builder<Referral>
     */
    private function unpaidReferrals(User $user): Builder
    {
        return Referral::query()->where('referrer_id', $user->id)->where('is_confirmed', true)->whereNull('withdrawal_id');
    }

    private function money(string|int|float|null $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }

    public function store(Request $request, RequestPayout $requestPayout): JsonResponse
    {
        $withdrawal = $requestPayout->handle($request->user());

        return (new WithdrawalResource($withdrawal))->response()->setStatusCode(201);
    }
}
