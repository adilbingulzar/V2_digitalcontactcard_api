<?php

namespace App\Actions\Tips;

use App\Models\AppSetting;
use App\Models\User;
use App\Models\UserFee;
use App\Models\UserSetting;
use InvalidArgumentException;

/**
 * Prices a tip.
 *
 * - Platform fee: a percentage of the tip plus a fixed amount, from the user's
 *   own fee (admin override) or the platform default. The user's
 *   "tax_deduction" setting decides who pays it: "1" = the customer, on top of
 *   the tip; otherwise it is taken from the recipient's share.
 * - Payment method fee: a percentage plus a fixed amount, always paid by the
 *   customer and charged over the tip plus any platform fee they pay.
 */
class QuoteTip
{
    public function handle(User $recipient, int $tipCents, int $paymentMethodId): TipQuote
    {
        $method = config('payment_methods.'.$paymentMethodId);

        if (! is_array($method) || ! $method['public']) {
            throw new InvalidArgumentException("Unknown payment method {$paymentMethodId}.");
        }

        [$percent, $fixedCents] = $this->platformFeeRate($recipient);
        $platformFeeCents = $this->percentOf($tipCents, $percent) + $fixedCents;

        $customerPays = UserSetting::query()
            ->whereBelongsTo($recipient)
            ->where('key', 'tax_deduction')
            ->value('value') === '1';

        $base = $tipCents + ($customerPays ? $platformFeeCents : 0);
        $methodFeeCents = $this->percentOf($base, (string) $method['fee_percent']) + $this->cents((string) $method['fee_fixed']);

        return new TipQuote($tipCents, $platformFeeCents, $customerPays, $methodFeeCents);
    }

    /**
     * @return array{0: string, 1: int} percentage and fixed fee in cents
     */
    private function platformFeeRate(User $recipient): array
    {
        $override = UserFee::query()
            ->whereBelongsTo($recipient)
            ->where('type', 'tip_fees')
            ->where('is_active', true)
            ->latest('id')
            ->first();

        if ($override !== null) {
            return [(string) $override->percent_fee, $this->cents((string) $override->fixed_fee)];
        }

        $settings = AppSetting::current();

        return [(string) $settings->tip_fee_percent, $this->cents((string) $settings->tip_fee_fixed)];
    }

    /**
     * Percent of an amount in cents, rounded half up to a whole cent.
     */
    private function percentOf(int $cents, string $percent): int
    {
        return (int) round($cents * (float) $percent / 100, 0, PHP_ROUND_HALF_UP);
    }

    private function cents(string $euros): int
    {
        return (int) round((float) $euros * 100);
    }
}
