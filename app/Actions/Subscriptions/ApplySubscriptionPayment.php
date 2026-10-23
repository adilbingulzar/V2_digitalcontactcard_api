<?php

namespace App\Actions\Subscriptions;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Subscription;
use Carbon\CarbonImmutable;

/**
 * Brings a plan in line with its payment. Once paid, the plan starts today, or
 * the day after the user's current plan ends, so paying early never loses days.
 */
class ApplySubscriptionPayment
{
    public function __construct(private SubscriptionStatus $status) {}

    public function handle(Payment $payment): void
    {
        $subscription = Subscription::query()->where('payment_id', $payment->id)->first();
        if ($subscription === null) {
            return;
        }

        if ($payment->status !== PaymentStatus::Paid || $subscription->is_active) {
            $subscription->forceFill(['payment_status' => $payment->status])->save();

            return;
        }

        $current = $this->status->valid()->whereBelongsTo($subscription->user)->whereKeyNot($subscription->id)->orderByDesc('ends_on')->first();
        $startsOn = $current?->ends_on !== null && $current->ends_on->isFuture()
            ? CarbonImmutable::parse($current->ends_on)->addDay()
            : CarbonImmutable::today();

        $subscription->forceFill([
            'payment_status' => PaymentStatus::Paid,
            'is_active' => true,
            'starts_on' => $startsOn,
            'ends_on' => $subscription->plan === 'yearly' ? $startsOn->addYear()->subDay() : $startsOn->addMonth()->subDay(),
        ])->save();
    }
}
