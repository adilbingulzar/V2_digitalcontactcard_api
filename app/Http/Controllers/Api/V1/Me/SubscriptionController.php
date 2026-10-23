<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Actions\Subscriptions\StartSubscriptionCheckout;
use App\Actions\Subscriptions\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Services\PayNl\PayNlException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The signed-in user's plan (the old "Subscription" page). A plan is only
 * needed to transfer the balance, and only when admins switched plans on.
 */
class SubscriptionController extends Controller
{
    public function show(Request $request, SubscriptionStatus $status): JsonResponse
    {
        $user = $request->user();

        return response()->json(['data' => [
            ...$status->for($user),
            'payment_methods' => collect(config('payment_methods'))
                ->filter(fn (array $method): bool => $method['public'])
                ->map(fn (array $method, int $id): array => ['id' => $id, 'name' => $method['name'], 'fee_percent' => $method['fee_percent'], 'fee_fixed' => $method['fee_fixed']])
                ->values(),
            'history' => Subscription::query()
                ->whereBelongsTo($user)
                ->where(fn ($plans) => $plans->where('is_active', true)->orWhere('payment_status', 'paid'))
                ->latest('id')
                ->limit(24)
                ->get()
                ->map(fn (Subscription $plan): array => [
                    'id' => $plan->id,
                    'plan' => $plan->plan,
                    'amount' => $plan->amount,
                    'starts_on' => $plan->starts_on?->toDateString(),
                    'ends_on' => $plan->ends_on?->toDateString(),
                    'granted' => $plan->pay_order_id === null,
                ]),
        ]]);
    }

    public function checkout(Request $request, StartSubscriptionCheckout $checkout, SubscriptionStatus $status): JsonResponse
    {
        $validated = $request->validate([
            'plan' => ['required', 'in:monthly,yearly'],
            'payment_method_id' => ['required', 'integer'],
        ]);
        abort_unless($status->for($request->user())['required'], 422, 'Plans are not in use at the moment.');

        try {
            $result = $checkout->handle($request->user(), $validated['plan'], (int) $validated['payment_method_id'], $request->ip());
        } catch (PayNlException $exception) {
            report($exception);

            return response()->json(['message' => 'The payment could not be started. Please try again later.'], 502);
        }

        return response()->json(['data' => ['payment_id' => $result['payment']->uuid, 'redirect_url' => $result['redirect_url']]], 201);
    }
}
