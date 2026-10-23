<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Actions\Tips\QuoteTip;
use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Spot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SpotQuoteController extends Controller
{
    /**
     * What the customer will pay for a tip at a spot. Fees follow the business owner's settings.
     */
    public function show(Request $request, Business $business, Spot $spot, QuoteTip $quoteTip): JsonResponse
    {
        $owner = $business->owner;

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:'.config('tiqs.tips.min_amount'), 'max:'.config('tiqs.tips.max_amount')],
            'payment_method_id' => ['required', 'integer', Rule::in(array_keys($owner->offeredPaymentMethods()))],
        ]);

        $quote = $quoteTip->handle($owner, (int) round((float) $validated['amount'] * 100), (int) $validated['payment_method_id']);

        return response()->json(['data' => $quote->toArray()]);
    }
}
