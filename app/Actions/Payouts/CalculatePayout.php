<?php

namespace App\Actions\Payouts;

use App\Models\Referral;
use App\Models\User;
use App\Models\UserFee;
use App\Models\UserSetting;

/**
 * Works out what a user can be paid out now: their balance (paid tips not yet
 * paid out, plus confirmed referral rewards), the payout fee for their
 * schedule, and the amount that reaches their bank.
 */
class CalculatePayout
{
    public const array SCHEDULES = ['manual', 'daily', 'weekly', 'monthly'];

    /**
     * @return array{schedule: string, balance_cents: int, fee_cents: int, net_cents: int, minimum_cents: int}
     */
    public function handle(User $user): array
    {
        $tips = (string) $user->receivedTips()->availableForPayout()->sum('amount');
        $referrals = (string) Referral::query()
            ->where('referrer_id', $user->id)
            ->where('is_confirmed', true)
            ->whereNull('withdrawal_id')
            ->sum('amount');

        return $this->forBalance($user, $this->cents($tips) + $this->cents($referrals));
    }

    /**
     * Fee and net amount for a given balance, on the user's schedule.
     *
     * @return array{schedule: string, balance_cents: int, fee_cents: int, net_cents: int, minimum_cents: int}
     */
    public function forBalance(User $user, int $balanceCents): array
    {
        $schedule = $this->schedule($user);
        [$percent, $fixedCents] = $this->feeRate($user, $schedule);

        $feeCents = $balanceCents === 0 ? 0 : min($balanceCents, (int) round($balanceCents * (float) $percent / 100) + $fixedCents);

        return [
            'schedule' => $schedule,
            'balance_cents' => $balanceCents,
            'fee_cents' => $feeCents,
            'net_cents' => $balanceCents - $feeCents,
            'minimum_cents' => $this->cents((string) config('tiqs.payouts.minimum')),
        ];
    }

    /**
     * The user's payout schedule: "manual" or how often automatic payouts run.
     */
    public function schedule(User $user): string
    {
        $setting = UserSetting::query()->whereBelongsTo($user)->where('key', 'payout')->first();

        if ($setting?->value === 'auto' && in_array($setting->sub_value, ['daily', 'weekly', 'monthly'], true)) {
            return $setting->sub_value;
        }

        return 'manual';
    }

    /**
     * @return array{0: string, 1: int} percent and fixed fee in cents
     */
    private function feeRate(User $user, string $schedule): array
    {
        $override = UserFee::query()
            ->whereBelongsTo($user)
            ->where('type', 'payout')
            ->where('applies_to', $schedule)
            ->where('is_active', true)
            ->latest('id')
            ->first();

        if ($override !== null) {
            return [(string) $override->percent_fee, $this->cents((string) $override->fixed_fee)];
        }

        $default = config('tiqs.payouts.fees.'.$schedule);

        return [$default['percent'], $this->cents($default['fixed'])];
    }

    private function cents(string $euros): int
    {
        return (int) round((float) $euros * 100);
    }
}
