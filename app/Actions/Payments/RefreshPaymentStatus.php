<?php

namespace App\Actions\Payments;

use App\Actions\Notifications\NotifyUser;
use App\Actions\Subscriptions\ApplySubscriptionPayment;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Tip;
use App\Services\PayNl\PayNlClient;
use App\Services\PayNl\PayNlException;
use Illuminate\Support\Facades\DB;

/**
 * Asks Pay.nl for a payment's real status and applies it to the payment, its
 * tips and its fees, or to the plan it pays for.
 *
 * This is the only code that marks money as paid. Webhook payloads and return
 * URLs only tell us which payment to check; their content is never trusted.
 */
class RefreshPaymentStatus
{
    public function __construct(
        private PayNlClient $payNl,
        private NotifyUser $notifyUser,
        private ApplySubscriptionPayment $applySubscription,
    ) {}

    /**
     * @throws PayNlException
     */
    public function handle(Payment $payment): Payment
    {
        if ($payment->pay_order_id === null) {
            return $payment;
        }

        $code = $this->payNl->orderStatusCode($payment->pay_order_id);
        $status = PaymentStatus::fromPayNlCode($code);

        $becamePaid = DB::transaction(function () use ($payment, $code, $status): bool {
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            $wasPaid = $locked->status === PaymentStatus::Paid;
            $paidAt = $status === PaymentStatus::Paid ? ($locked->paid_at ?? now()) : $locked->paid_at;

            $locked->forceFill([
                'status' => $status,
                'pay_status_code' => $code,
                'paid_at' => $paidAt,
                'status_checked_at' => now(),
            ])->save();

            // paid_at stays set after a refund, so reports still show when the tip was paid.
            $locked->tips()->update([
                'payment_status' => $status,
                'paid_at' => $paidAt,
            ]);
            $locked->feeCollections()->update(['payment_status' => $status]);

            return ! $wasPaid && $status === PaymentStatus::Paid;
        });

        $payment->refresh();

        if ($payment->purpose === 'subscription') {
            $this->applySubscription->handle($payment);
        } elseif ($becamePaid) {
            $this->notifyRecipients($payment);
        }

        return $payment;
    }

    /**
     * The person who was tipped hears about "a tip"; team members who share in it
     * (or the staff of a spot) hear about "a team tip".
     */
    private function notifyRecipients(Payment $payment): void
    {
        $payment->tips()->whereNotNull('recipient_id')->get()->each(function (Tip $tip) use ($payment): void {
            $isOwnTip = $payment->spot_id === null && $tip->recipient_id === $payment->recipient_id;

            $this->notifyUser->handle($tip->recipient_id, $isOwnTip ? 'service_tip_self' : 'service_tip_group', [
                'title' => 'Tip received',
                'message' => ($isOwnTip ? 'You received a tip of € ' : 'Your share of a team tip: € ').$tip->amount.'.',
                'icon' => 'fa-circle-dollar-to-slot',
                'business_id' => $tip->business_id,
            ]);
        });
    }
}
