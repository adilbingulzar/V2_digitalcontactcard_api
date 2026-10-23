<?php

namespace App\Http\Controllers\Api\V1\Webhooks;

use App\Actions\Payments\RefreshPaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\PayNl\PayNlException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PayNlWebhookController extends Controller
{
    /**
     * Pay.nl exchange call: a payment's status changed.
     *
     * The payload is only used to find the payment; its status is fetched from
     * Pay.nl's API with our own credentials, so a forged call cannot mark a
     * payment as paid. Answering {"result": false} makes Pay.nl retry later.
     *
     * @see https://developer.pay.nl/docs/handling-the-exchange-calls
     */
    public function __invoke(Request $request, RefreshPaymentStatus $refresh): JsonResponse
    {
        $orderId = $request->input('object.id') ?? $request->input('order_id');
        $reference = $request->input('object.reference') ?? $request->input('extra1');

        $payment = match (true) {
            is_string($orderId) && $orderId !== '' => Payment::where('pay_order_id', $orderId)->first(),
            is_string($reference) && $reference !== '' => Payment::where('uuid', $reference)->first(),
            default => null,
        };

        if ($payment === null) {
            Log::warning('Pay.nl exchange call for an unknown payment.', ['order_id' => $orderId, 'reference' => $reference]);

            return response()->json(['result' => true, 'message' => 'Unknown payment, ignored.']);
        }

        try {
            $refresh->handle($payment);
        } catch (PayNlException $exception) {
            report($exception);

            return response()->json(['result' => false, 'message' => 'Status could not be checked, please retry.'], 503);
        }

        return response()->json(['result' => true]);
    }
}
