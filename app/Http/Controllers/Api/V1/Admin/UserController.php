<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Subscriptions\SubscriptionStatus;
use App\Enums\AccountType;
use App\Enums\PaymentStatus;
use App\Models\Tip;
use App\Models\User;
use App\Models\UserFee;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Users with what they received (the old "Tip counter"), and their own fees
 * (the old "Service fees" and "User fees" pages).
 */
class UserController extends AdminController
{
    private const array SCHEDULES = ['manual', 'daily', 'weekly', 'monthly'];

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', Rule::enum(AccountType::class)],
            'sort' => ['nullable', 'in:newest,tips'],
            'subscribed' => ['nullable', 'in:yes,no'],
        ]);
        // Plans running today, as on the old "Subscription List" page.
        $runningPlan = fn (Builder $plans) => $plans
            ->where('is_active', true)
            ->whereDate('starts_on', '<=', today())
            ->whereDate('ends_on', '>=', today())
            ->where(fn (Builder $paid) => $paid->where('payment_status', PaymentStatus::Paid)->orWhereNull('pay_order_id'));
        $paid = fn (Builder $tips) => $tips->paid()->where('amount', '>', 0);

        $users = User::query()
            ->when($validated['q'] ?? null, fn (Builder $users, string $q) => $users->where(fn (Builder $match) => $match
                ->where('name', 'like', '%'.$q.'%')
                ->orWhere('email', 'like', '%'.$q.'%')
                ->orWhere('slug', 'like', '%'.$q.'%')))
            ->when($validated['type'] ?? null, fn (Builder $users, string $type) => $users->where('account_type', $type))
            ->when(($validated['subscribed'] ?? null) === 'yes', fn (Builder $users) => $users->whereHas('subscriptions', $runningPlan))
            ->when(($validated['subscribed'] ?? null) === 'no', fn (Builder $users) => $users->whereDoesntHave('subscriptions', $runningPlan))
            ->withMax(['subscriptions as plan_ends_on' => $runningPlan], 'ends_on')
            ->withSum(['receivedTips as tips_total' => $paid], 'amount')
            ->withCount(['receivedTips as tips_count' => $paid])
            ->when(($validated['sort'] ?? 'newest') === 'tips', fn (Builder $users) => $users->orderByDesc('tips_total'), fn (Builder $users) => $users->latest('id'))
            ->paginate(25)
            ->withQueryString();

        return $this->paginated($users, fn (User $user): array => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'slug' => $user->slug,
            'account_type' => $user->account_type,
            'role' => $user->role,
            'status' => $user->status,
            'tips_total' => $this->money($user->tips_total),
            'tips_count' => (int) $user->tips_count,
            'plan_ends_on' => $user->plan_ends_on === null ? null : CarbonImmutable::parse($user->plan_ends_on)->toDateString(),
            'created_at' => $user->created_at?->toIso8601String(),
        ]);
    }

    public function show(User $user, SubscriptionStatus $subscriptionStatus): JsonResponse
    {
        $user->load('business', 'bankAccount');
        $fees = UserFee::query()->whereBelongsTo($user)->where('is_active', true)->get();
        $fee = fn (string $type, ?string $appliesTo) => $fees->first(fn (UserFee $fee): bool => $fee->type === $type && $fee->applies_to === $appliesTo);
        $feeData = fn (?UserFee $fee): ?array => $fee === null ? null : ['percent' => $this->money($fee->percent_fee), 'fixed' => $this->money($fee->fixed_fee)];

        return response()->json(['data' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified' => $user->hasVerifiedEmail(),
            'slug' => $user->slug,
            'phone' => filled($user->phone) ? '+'.$user->country_code.' '.$user->phone : null,
            'country' => $user->country,
            'account_type' => $user->account_type,
            'role' => $user->role,
            'status' => $user->status,
            'business' => $user->business?->only(['id', 'name', 'code', 'is_active']),
            'bank_account' => $user->bankAccount === null ? null : [
                'account_name' => $user->bankAccount->account_name,
                'bank_name' => $user->bankAccount->bank_name,
                'account_number' => $user->bankAccount->maskedAccountNumber(),
            ],
            'balance' => $this->money($user->receivedTips()->availableForPayout()->sum('amount')),
            'tips_total' => $this->money(Tip::query()->whereBelongsTo($user, 'recipient')->paid()->sum('amount')),
            'fees' => [
                'tip' => $feeData($fee('tip_fees', null)),
                'payout' => collect(self::SCHEDULES)->mapWithKeys(fn (string $schedule): array => [$schedule => $feeData($fee('payout', $schedule))])->all(),
            ],
            'default_fees' => [
                'payout' => config('tiqs.payouts.fees'),
            ],
            'subscription' => $subscriptionStatus->for($user),
            'identity_status' => $user->identity_status ?? 'none',
            'created_at' => $user->created_at?->toIso8601String(),
        ]]);
    }

    /**
     * Own fees for one user. A fee left empty (null) falls back to the platform fee.
     */
    public function updateFees(Request $request, User $user, SubscriptionStatus $subscriptionStatus): JsonResponse
    {
        $feeRules = fn (string $prefix): array => [
            $prefix => ['nullable', 'array'],
            $prefix.'.percent' => ['required_with:'.$prefix, 'numeric', 'between:0,100'],
            $prefix.'.fixed' => ['required_with:'.$prefix, 'numeric', 'between:0,100'],
        ];
        $validated = $request->validate([
            ...$feeRules('tip'),
            'payout' => ['nullable', 'array:'.implode(',', self::SCHEDULES)],
            ...collect(self::SCHEDULES)->flatMap(fn (string $schedule): array => $feeRules('payout.'.$schedule))->all(),
        ]);

        DB::transaction(function () use ($user, $validated): void {
            $this->saveFee($user, 'tip_fees', null, $validated['tip'] ?? null);
            foreach (self::SCHEDULES as $schedule) {
                $this->saveFee($user, 'payout', $schedule, $validated['payout'][$schedule] ?? null);
            }
        });

        return $this->show($user, $subscriptionStatus);
    }

    /**
     * @param  array{percent: numeric-string|float, fixed: numeric-string|float}|null  $fee
     */
    private function saveFee(User $user, string $type, ?string $appliesTo, ?array $fee): void
    {
        $existing = UserFee::query()->whereBelongsTo($user)->where('type', $type)
            ->when($appliesTo === null, fn (Builder $fees) => $fees->whereNull('applies_to'), fn (Builder $fees) => $fees->where('applies_to', $appliesTo));

        if ($fee === null) {
            $existing->delete();

            return;
        }

        $row = $existing->latest('id')->first() ?? new UserFee;
        $row->forceFill([
            'user_id' => $user->id,
            'type' => $type,
            'applies_to' => $appliesTo,
            'percent_fee' => $fee['percent'],
            'fixed_fee' => $fee['fixed'],
            'is_active' => true,
        ])->save();
    }
}
