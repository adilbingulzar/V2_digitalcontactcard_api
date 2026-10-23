<?php

namespace App\Actions\Subscriptions;

use App\Enums\PaymentStatus;
use App\Models\AppSetting;
use App\Models\Subscription;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Whether a user needs a plan, and has one. A plan is only needed to request
 * payouts, and only when admins switched plans on for personal or business
 * accounts. New accounts get a free trial first.
 */
class SubscriptionStatus
{
    /**
     * @return array{required: bool, active: bool, allows_payout: bool, trial_ends_on: ?string, plan: ?string, ends_on: ?string, granted: bool, prices: array{monthly: string, yearly: string}}
     */
    public function for(User $user): array
    {
        $settings = AppSetting::current();
        $isBusiness = $user->business()->exists();
        $required = $isBusiness ? $settings->business_subscription_required : $settings->user_subscription_required;

        $trialEnds = CarbonImmutable::parse($user->created_at)->startOfDay()->addDays($settings->free_trial_days);
        $inTrial = $settings->free_trial_days > 0 && $trialEnds->isFuture();
        $current = $this->current($user);

        return [
            'required' => $required,
            'active' => $current !== null,
            'allows_payout' => ! $required || $inTrial || $current !== null,
            'trial_ends_on' => $inTrial ? $trialEnds->toDateString() : null,
            'plan' => $current?->plan,
            'ends_on' => $current?->ends_on?->toDateString(),
            'granted' => $current !== null && $current->pay_order_id === null,
            'prices' => [
                'monthly' => (string) ($isBusiness ? $settings->business_plan_monthly : $settings->user_plan_monthly),
                'yearly' => (string) ($isBusiness ? $settings->business_plan_yearly : $settings->user_plan_yearly),
            ],
        ];
    }

    public function allowsPayout(User $user): bool
    {
        return $this->for($user)['allows_payout'];
    }

    /**
     * The plan running today that lasts longest: paid online, or given by an admin.
     */
    public function current(User $user): ?Subscription
    {
        return $this->valid()
            ->whereBelongsTo($user)
            ->whereDate('starts_on', '<=', today())
            ->orderByDesc('ends_on')
            ->first();
    }

    /**
     * Active plans that are paid, or were given without payment by an admin.
     *
     * @return Builder<Subscription>
     */
    public function valid(): Builder
    {
        return Subscription::query()
            ->where('is_active', true)
            ->whereDate('ends_on', '>=', today())
            ->where(fn (Builder $plans) => $plans->where('payment_status', PaymentStatus::Paid)->orWhereNull('pay_order_id'));
    }
}
