<?php

namespace App\Actions\Tips;

use App\Enums\PaymentStatus;
use App\Enums\TipType;
use App\Models\FeeCollection;
use App\Models\Payment;
use App\Models\Spot;
use App\Models\Tip;
use App\Models\User;
use App\Services\PayNl\PayNlClient;
use App\Services\PayNl\PayNlException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates a pending payment for a tip to a person and starts it at Pay.nl.
 *
 * Every member who shares in the tip gets a pending tip row; nothing counts
 * towards a balance until RefreshPaymentStatus has confirmed the payment with Pay.nl.
 */
class StartTipCheckout
{
    public function __construct(
        private SplitTip $splitTip,
        private QuoteTip $quoteTip,
        private PayNlClient $payNl,
    ) {}

    /**
     * @param  array{amount: string, payment_method_id: int, email?: ?string, name?: ?string, rating?: ?int, review?: ?string}  $input
     * @return array{payment: Payment, redirect_url: string}
     *
     * @throws PayNlException
     */
    public function handle(User $recipient, array $input, ?string $ipAddress): array
    {
        $tipCents = $this->cents($input['amount']);
        $quote = $this->quoteTip->handle($recipient, $tipCents, (int) $input['payment_method_id']);

        // A platform fee paid by the recipient comes off their own share only.
        $shares = $this->splitTip->split($recipient, $tipCents);
        $shares[$recipient->id]['amount_cents'] = max(0, $shares[$recipient->id]['amount_cents'] - $quote->recipientPlatformFeeCents());

        return $this->start($recipient, null, $quote, $shares, $recipient->id, $input, $ipAddress, 'Tip for '.$recipient->name);
    }

    /**
     * A tip given at a spot. Fees follow the business owner's settings; a platform
     * fee paid by the staff comes off every share in proportion.
     *
     * @param  array{amount: string, payment_method_id: int, email?: ?string, name?: ?string, rating?: ?int, review?: ?string}  $input
     * @return array{payment: Payment, redirect_url: string}
     *
     * @throws PayNlException
     */
    public function handleForSpot(Spot $spot, array $input, ?string $ipAddress): array
    {
        $owner = $spot->business->owner;
        $tipCents = $this->cents($input['amount']);
        $quote = $this->quoteTip->handle($owner, $tipCents, (int) $input['payment_method_id']);
        $shares = $this->splitTip->splitForSpot($spot, $tipCents - $quote->recipientPlatformFeeCents());

        return $this->start($owner, $spot, $quote, $shares, (int) array_key_first($shares), $input, $ipAddress, 'Tip at '.$spot->name);
    }

    /**
     * @param  array<int, array{business_id: ?int, amount_cents: int}>  $shares  net amounts per user
     * @param  int  $reviewOwnerId  the share that carries the customer's rating and review
     * @param  array{amount: string, payment_method_id: int, email?: ?string, name?: ?string, rating?: ?int, review?: ?string}  $input
     * @return array{payment: Payment, redirect_url: string}
     *
     * @throws PayNlException
     */
    private function start(User $recipient, ?Spot $spot, TipQuote $quote, array $shares, int $reviewOwnerId, array $input, ?string $ipAddress, string $description): array
    {
        $payment = DB::transaction(function () use ($recipient, $spot, $input, $ipAddress, $quote, $shares, $reviewOwnerId): Payment {
            $payment = new Payment;
            $payment->forceFill([
                'uuid' => (string) Str::uuid(),
                'purpose' => 'tip',
                'recipient_id' => $recipient->id,
                'spot_id' => $spot?->id,
                'pay_method_id' => (int) $input['payment_method_id'],
                'status' => PaymentStatus::Pending,
                'tip_amount' => TipQuote::euros($quote->tipCents),
                'platform_fee' => TipQuote::euros($quote->customerPlatformFeeCents()),
                'method_fee' => TipQuote::euros($quote->methodFeeCents),
                'total_amount' => TipQuote::euros($quote->totalCents()),
                'currency' => config('tiqs.tips.currency'),
                'payer_email' => $input['email'] ?? null,
                'ip_address' => $ipAddress,
            ])->save();

            $reviewTip = null;
            foreach ($shares as $userId => $share) {
                $carriesReview = $userId === $reviewOwnerId;
                $tip = $this->pendingTip($payment, $userId, $share['business_id'], $share['amount_cents'], $ipAddress, $carriesReview ? $input : []);
                $reviewTip = $carriesReview ? $tip : $reviewTip;
            }

            $this->recordPlatformFee($payment, $recipient, $reviewTip, $quote);

            return $payment;
        });

        try {
            $order = $this->payNl->createOrder([
                'amount_cents' => $quote->totalCents(),
                'currency' => $payment->currency,
                'description' => Str::limit($description, 32, ''),
                'reference' => $payment->uuid,
                'return_url' => config('tiqs.frontend_url').'/payment/'.$payment->uuid,
                'exchange_url' => route('api.v1.webhooks.paynl'),
                'payment_method_id' => $payment->pay_method_id,
                'email' => $payment->payer_email,
                'ip_address' => $ipAddress,
            ]);
        } catch (PayNlException $exception) {
            $this->markFailed($payment);

            throw $exception;
        }

        $payment->forceFill(['pay_order_id' => $order->id, 'pay_status_code' => $order->statusCode])->save();

        if ($order->redirectUrl === null) {
            $this->markFailed($payment);

            throw new PayNlException('Pay.nl did not return a payment page for order '.$order->id.'.');
        }

        return ['payment' => $payment, 'redirect_url' => $order->redirectUrl];
    }

    /**
     * @param  array{email?: ?string, name?: ?string, rating?: ?int, review?: ?string}  $review  only on the recipient's row
     */
    private function pendingTip(Payment $payment, int $userId, ?int $businessId, int $netCents, ?string $ipAddress, array $review): Tip
    {
        $tip = new Tip([
            'type' => TipType::Online,
            'recipient_id' => $userId,
            'business_id' => $businessId,
            'spot_id' => $payment->spot_id,
            'amount' => TipQuote::euros($netCents),
            'currency' => $payment->currency,
            'rating' => $review['rating'] ?? null,
            'review' => $review['review'] ?? null,
            'reviewer_name' => $review['name'] ?? null,
            'reviewer_email' => $review['email'] ?? null,
            'ip_address' => $ipAddress,
        ]);
        $tip->forceFill([
            'payment_id' => $payment->id,
            'payment_status' => PaymentStatus::Pending,
            'token' => Str::random(40),
        ])->save();

        return $tip;
    }

    private function cents(string|int|float $euros): int
    {
        return (int) round((float) $euros * 100);
    }

    private function recordPlatformFee(Payment $payment, User $recipient, ?Tip $recipientTip, TipQuote $quote): void
    {
        if ($quote->platformFeeCents === 0) {
            return;
        }

        $fee = new FeeCollection;
        $fee->forceFill([
            'user_id' => $recipient->id,
            'payment_id' => $payment->id,
            'tip_id' => $recipientTip?->id,
            'fee_type' => $quote->customerPaysPlatformFee ? 2 : 1,
            'tip_amount' => TipQuote::euros($quote->tipCents),
            'fee_amount' => TipQuote::euros($quote->platformFeeCents),
            'tax_amount' => 0,
            'payment_status' => PaymentStatus::Pending,
        ])->save();
    }

    private function markFailed(Payment $payment): void
    {
        DB::transaction(function () use ($payment): void {
            $payment->forceFill(['status' => PaymentStatus::Failed])->save();
            $payment->tips()->update(['payment_status' => PaymentStatus::Failed]);
            $payment->feeCollections()->update(['payment_status' => PaymentStatus::Failed]);
        });
    }
}
