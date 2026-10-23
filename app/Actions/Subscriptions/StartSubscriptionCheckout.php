<?php

namespace App\Actions\Subscriptions;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Services\PayNl\PayNlClient;
use App\Services\PayNl\PayNlException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Starts paying for a monthly or yearly plan. The price always comes from the
 * settings (the old app took it from the URL), and the plan only starts once
 * Pay.nl confirms the payment.
 */
class StartSubscriptionCheckout
{
    public function __construct(
        private SubscriptionStatus $status,
        private PayNlClient $payNl,
    ) {}

    /**
     * @return array{payment: Payment, redirect_url: string}
     *
     * @throws ValidationException
     * @throws PayNlException
     */
    public function handle(User $user, string $plan, int $paymentMethodId, ?string $ipAddress): array
    {
        $method = config('payment_methods.'.$paymentMethodId);
        if (! is_array($method) || ! $method['public']) {
            throw ValidationException::withMessages(['payment_method_id' => 'Choose a payment method.']);
        }

        $priceCents = (int) round((float) $this->status->for($user)['prices'][$plan] * 100);
        $methodFeeCents = (int) round($priceCents * (float) $method['fee_percent'] / 100) + (int) round((float) $method['fee_fixed'] * 100);
        $euros = fn (int $cents): string => number_format($cents / 100, 2, '.', '');

        $payment = DB::transaction(function () use ($user, $plan, $paymentMethodId, $ipAddress, $priceCents, $methodFeeCents, $euros): Payment {
            $payment = new Payment;
            $payment->forceFill([
                'uuid' => (string) Str::uuid(),
                'purpose' => 'subscription',
                'recipient_id' => $user->id,
                'pay_method_id' => $paymentMethodId,
                'status' => PaymentStatus::Pending,
                'tip_amount' => $euros($priceCents),
                'platform_fee' => 0,
                'method_fee' => $euros($methodFeeCents),
                'total_amount' => $euros($priceCents + $methodFeeCents),
                'currency' => config('tiqs.tips.currency'),
                'payer_email' => $user->email,
                'ip_address' => $ipAddress,
            ])->save();

            $subscription = new Subscription;
            $subscription->forceFill([
                'user_id' => $user->id,
                'payment_id' => $payment->id,
                'plan' => $plan,
                'amount' => $euros($priceCents),
                'payment_status' => PaymentStatus::Pending,
                'reference' => $payment->uuid,
                'is_active' => false,
            ])->save();

            return $payment;
        });

        $order = $this->payNl->createOrder([
            'amount_cents' => $priceCents + $methodFeeCents,
            'currency' => $payment->currency,
            'description' => Str::limit(config('app.name').' '.$plan.' plan', 32, ''),
            'reference' => $payment->uuid,
            'return_url' => config('tiqs.frontend_url').'/payment/'.$payment->uuid,
            'exchange_url' => route('api.v1.webhooks.paynl'),
            'payment_method_id' => $paymentMethodId,
            'email' => $user->email,
            'ip_address' => $ipAddress,
        ]);

        $payment->forceFill(['pay_order_id' => $order->id, 'pay_status_code' => $order->statusCode])->save();
        Subscription::query()->where('payment_id', $payment->id)->update(['pay_order_id' => Str::limit($order->id, 40, '')]);

        if ($order->redirectUrl === null) {
            throw new PayNlException('Pay.nl did not return a payment page for order '.$order->id.'.');
        }

        return ['payment' => $payment, 'redirect_url' => $order->redirectUrl];
    }
}
