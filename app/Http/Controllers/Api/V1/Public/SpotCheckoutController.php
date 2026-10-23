<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Actions\Tips\SplitTip;
use App\Actions\Tips\StartTipCheckout;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\TipCheckoutRequest;
use App\Models\Business;
use App\Models\Spot;
use App\Services\PayNl\PayNlException;
use Illuminate\Http\JsonResponse;

class SpotCheckoutController extends Controller
{
    /**
     * Start paying a tip at a spot. The response carries the Pay.nl page to send the customer to.
     */
    public function store(TipCheckoutRequest $request, Business $business, Spot $spot, SplitTip $splitTip, StartTipCheckout $startCheckout): JsonResponse
    {
        if (! $splitTip->canReceiveSpotTips($spot)) {
            return response()->json(['message' => 'Tips cannot be given at this spot right now.'], 422);
        }

        try {
            $checkout = $startCheckout->handleForSpot($spot, $request->validated(), $request->ip());
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
