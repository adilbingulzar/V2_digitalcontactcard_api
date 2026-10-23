<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Actions\Payments\RefreshPaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\PublicPaymentResource;
use App\Models\Payment;
use App\Services\PayNl\PayNlException;

class PaymentController extends Controller
{
    /**
     * Status of a checkout, for the page the customer returns to from Pay.nl.
     * A payment that is not final yet is checked with Pay.nl first, so the page
     * does not depend on the webhook having arrived.
     */
    public function show(Payment $payment, RefreshPaymentStatus $refresh): PublicPaymentResource
    {
        if (! $payment->status->isFinal()) {
            try {
                $payment = $refresh->handle($payment);
            } catch (PayNlException $exception) {
                report($exception);
            }
        }

        return new PublicPaymentResource($payment->load('recipient.tipPageSetting', 'spot.business'));
    }
}
