<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Subscriptions\SubscriptionStatus;
use App\Enums\PaymentStatus;
use App\Models\Subscription;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Plans of all users (the old admin "Subscription" page). Admins can give a
 * user a free plan, or end a plan early.
 */
class SubscriptionController extends AdminController
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'in:active,expired,pending,all'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $status = $validated['status'] ?? 'active';

        $page = Subscription::query()
            ->when($status === 'active', fn (Builder $plans) => $plans->where('is_active', true)->whereDate('ends_on', '>=', today()))
            ->when($status === 'expired', fn (Builder $plans) => $plans->where('is_active', true)->whereDate('ends_on', '<', today()))
            ->when($status === 'pending', fn (Builder $plans) => $plans->where('is_active', false))
            ->when($validated['q'] ?? null, fn (Builder $plans, string $q) => $plans->whereHas('user', fn (Builder $users) => $users->where('name', 'like', '%'.$q.'%')->orWhere('email', 'like', '%'.$q.'%')))
            ->with('user:id,name,email')
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        return $this->paginated($page, fn (Subscription $plan): array => [
            'id' => $plan->id,
            'user' => $plan->user?->only(['id', 'name', 'email']),
            'plan' => $plan->plan,
            'amount' => $plan->amount,
            'payment_status' => $plan->payment_status,
            'granted' => $plan->pay_order_id === null,
            'is_active' => $plan->is_active,
            'starts_on' => $plan->starts_on?->toDateString(),
            'ends_on' => $plan->ends_on?->toDateString(),
            'created_at' => $plan->created_at?->toIso8601String(),
        ]);
    }

    /**
     * Give a user a free plan for some months, starting after any plan they already have.
     */
    public function store(Request $request, SubscriptionStatus $status): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'months' => ['required', 'integer', 'between:1,60'],
        ]);
        $user = User::findOrFail($validated['user_id']);

        $current = $status->current($user);
        $startsOn = $current?->ends_on !== null ? CarbonImmutable::parse($current->ends_on)->addDay() : CarbonImmutable::today();

        $plan = new Subscription;
        $plan->forceFill([
            'user_id' => $user->id,
            'plan' => 'free',
            'amount' => 0,
            'payment_status' => PaymentStatus::Paid,
            'is_active' => true,
            'starts_on' => $startsOn,
            'ends_on' => $startsOn->addMonths($validated['months'])->subDay(),
            'modified_by' => $request->user()->id,
        ])->save();

        return response()->json(['data' => ['id' => $plan->id, 'starts_on' => $plan->starts_on->toDateString(), 'ends_on' => $plan->ends_on->toDateString()]], 201);
    }

    /**
     * End a plan today.
     */
    public function destroy(Request $request, Subscription $subscription): Response
    {
        $subscription->forceFill([
            'is_active' => false,
            'ends_on' => min($subscription->ends_on ?? today(), today()->subDay()),
            'modified_by' => $request->user()->id,
        ])->save();

        return response()->noContent();
    }
}
