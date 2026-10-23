<?php

namespace App\Actions\Payouts;

use App\Actions\Notifications\NotifyUser;
use App\Actions\Subscriptions\SubscriptionStatus;
use App\Enums\WithdrawalStatus;
use App\Mail\PayoutRequestedMail;
use App\Models\Referral;
use App\Models\Tip;
use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Turns a user's balance into a payout request for the admins to pay.
 *
 * Only paid tips and confirmed referral rewards are included (the old app also
 * swept up tips that were never paid). The user row is locked while the
 * request is made, so two requests at the same moment cannot claim the same money.
 */
class RequestPayout
{
    public function __construct(
        private CalculatePayout $calculate,
        private SubscriptionStatus $subscriptionStatus,
    ) {}

    /**
     * @throws ValidationException
     */
    public function handle(User $user): Withdrawal
    {
        if (! $user->bankAccount()->exists()) {
            throw ValidationException::withMessages(['payout' => 'Add your bank account before requesting a payout.']);
        }
        if (! $this->subscriptionStatus->allowsPayout($user)) {
            throw ValidationException::withMessages(['payout' => 'Choose a plan to transfer your balance.']);
        }

        $withdrawal = DB::transaction(function () use ($user): Withdrawal {
            User::query()->whereKey($user->id)->lockForUpdate()->first();

            // Lock and read the exact rows that are paid out, so the amount always matches them.
            $tips = $user->receivedTips()->availableForPayout()->lockForUpdate()->get(['id', 'amount']);
            $referrals = Referral::query()
                ->where('referrer_id', $user->id)
                ->where('is_confirmed', true)
                ->whereNull('withdrawal_id')
                ->lockForUpdate()
                ->get(['id', 'amount']);

            $balanceCents = (int) round(($tips->sum('amount') + $referrals->sum('amount')) * 100);
            $payout = $this->calculate->forBalance($user, $balanceCents);

            if ($payout['net_cents'] < $payout['minimum_cents']) {
                throw ValidationException::withMessages([
                    'payout' => 'The minimum payout is € '.number_format($payout['minimum_cents'] / 100, 2, ',', '.').' after fees.',
                ]);
            }

            $withdrawal = new Withdrawal;
            $withdrawal->forceFill([
                'user_id' => $user->id,
                'reference' => Str::upper(Str::random(12)),
                'type' => 0,
                'amount' => number_format($payout['net_cents'] / 100, 2, '.', ''),
                'fee' => number_format($payout['fee_cents'] / 100, 2, '.', ''),
                'status' => WithdrawalStatus::Requested,
                'requested_at' => now(),
            ])->save();

            Tip::query()->whereKey($tips->modelKeys())->update(['withdrawal_id' => $withdrawal->id]);
            Referral::query()->whereKey($referrals->modelKeys())->update(['withdrawal_id' => $withdrawal->id]);

            app(NotifyUser::class)->handle($user->id, 'payment_processing', [
                'audience' => 'user',
                'title' => 'Payout requested',
                'message' => 'We will transfer € '.number_format($payout['net_cents'] / 100, 2, ',', '.').' to your bank account soon.',
                'icon' => 'fa-building-columns',
            ]);

            return $withdrawal;
        });

        if ($to = config('tiqs.payouts.notify_email')) {
            Mail::to($to)->send(new PayoutRequestedMail($withdrawal->load('user')));
        }

        return $withdrawal;
    }
}
