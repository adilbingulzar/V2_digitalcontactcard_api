<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Actions\Tips\SplitTip;
use App\Actions\Tips\StartTipCheckout;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\TipCheckoutRequest;
use App\Models\User;
use App\Services\PayNl\PayNlException;
use Illuminate\Http\JsonResponse;

class TipCheckoutController extends Controller
{
    /**
     * Start paying a tip. The response carries the Pay.nl page to send the customer to.
     */
    public function store(TipCheckoutRequest $request, User $user, SplitTip $splitTip, StartTipCheckout $startCheckout): JsonResponse
    {
        if (! $splitTip->canReceiveTips($user)) {
            return response()->json(['message' => 'This person cannot receive tips right now.'], 422);
        }

        try {
            $checkout = $startCheckout->handle($user, $request->validated(), $request->ip());
        } catch (PayNlException $exception) {
            report($exception);

            return response()->json(['message' => 'The payment could not be started. Please try again.'], 502);
        }

        return response()->json([
            'payment' => $checkout['payment']->uuid,
            'redirect_url' => $checkout['redirect_url'],
        ], 201);
    }
}
