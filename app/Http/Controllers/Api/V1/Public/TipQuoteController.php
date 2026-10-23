<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Actions\Tips\QuoteTip;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TipQuoteController extends Controller
{
    /**
     * What the customer will pay for a tip with a payment method, so the page
     * can show the total before they continue to Pay.nl.
     */
    public function show(Request $request, User $user, QuoteTip $quoteTip): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:'.config('tiqs.tips.min_amount'), 'max:'.config('tiqs.tips.max_amount')],
            'payment_method_id' => ['required', 'integer', Rule::in(array_keys($user->offeredPaymentMethods()))],
        ]);

        $quote = $quoteTip->handle($user, (int) round((float) $validated['amount'] * 100), (int) $validated['payment_method_id']);

        return response()->json(['data' => $quote->toArray()]);
    }
}
