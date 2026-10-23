<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Actions\Subscriptions\SubscriptionStatus;
use App\Enums\EmployeeStatus;
use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Employee;
use App\Models\Referral;
use App\Models\Tip;
use App\Models\User;
use App\Models\UserSetting;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Overview for the signed-in user: balance, tip totals, rating, recent
     * activity and what is left to set up. Days are counted in the business timezone.
     */
    public function show(Request $request, SubscriptionStatus $subscriptions): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $business = $user->business;
        $subscription = $subscriptions->for($user);
        $timezone = config('tiqs.business_timezone');
        $today = CarbonImmutable::now($timezone)->startOfDay();

        $tipBalance = (string) $user->receivedTips()->availableForPayout()->sum('amount');
        $referralBalance = (string) Referral::query()
            ->where('referrer_id', $user->id)
            ->where('is_confirmed', true)
            ->whereNull('withdrawal_id')
            ->sum('amount');

        return response()->json(['data' => [
            'balance' => [
                'tips' => $this->money($tipBalance),
                'referrals' => $this->money($referralBalance),
                'total' => $this->money(bcadd($tipBalance, $referralBalance, 2)),
            ],
            'totals' => [
                'today' => $this->paidBetween($user, $today, $today->addDay()),
                'yesterday' => $this->paidBetween($user, $today->subDay(), $today),
                'last_7_days' => $this->paidBetween($user, $today->subDays(6), $today->addDay()),
                'previous_7_days' => $this->paidBetween($user, $today->subDays(13), $today->subDays(6)),
                'this_month' => $this->paidBetween($user, $today->startOfMonth(), $today->addDay()),
                'this_week' => $this->paidBetween($user, $today->startOfWeek(), $today->addDay()),
                'last_week' => $this->paidBetween($user, $today->startOfWeek()->subWeek(), $today->startOfWeek()),
                'last_month' => $this->paidBetween($user, $today->startOfMonth()->subMonth(), $today->startOfMonth()),
            ],
            'reviews' => [
                'today' => $this->reviewsBetween($user, $today, $today->addDay()),
                'yesterday' => $this->reviewsBetween($user, $today->subDay(), $today),
            ],
            'trend' => $this->trend($user, $today),
            'before_trend' => $this->paidBetween($user, $today->subDays(7), $today->subDays(6))['amount'],
            'weekly' => $this->weekly($user, $today),
            'rating' => $this->rating($user),
            'recent' => $this->recent($user),
            'setup' => $setup = [
                'profile' => filled($user->bio) && filled($user->avatar),
                'bank_account' => $user->bankAccount()->exists(),
                'payout_settings' => UserSetting::query()->whereBelongsTo($user)->where('key', 'payout')->exists(),
                'tip_page' => $user->tipPageSetting()->exists(),
                'networks' => Employee::query()->whereBelongsTo($user)->where('status', EmployeeStatus::Accepted)->exists(),
            ],
            // As in the old app, joining a business is suggested but not needed to finish.
            'setup_done' => $setup['tip_page'] && $setup['bank_account'] && $setup['payout_settings'],
            'subscription_prompt' => $subscription['required'] && ! $subscription['active'],
            'business' => $business === null ? null : $this->business($business),
            'employments' => Employee::query()
                ->whereBelongsTo($user)
                ->where('status', EmployeeStatus::Accepted)
                ->with('business:id,name', 'role:id,name')
                ->get()
                ->map(fn (Employee $employment): array => [
                    'id' => $employment->id,
                    'business_name' => $employment->business->name,
                    'role' => $employment->role?->name,
                    'is_live' => $employment->is_live,
                ]),
        ]]);
    }

    /**
     * Setup steps and staff lists for a business owner.
     *
     * @return array<string, mixed>
     */
    private function business(Business $business): array
    {
        $employees = $business->employees()->with('user', 'role')->get();
        $agreements = $business->agreements()->get(['id', 'role_shares']);

        $setup = [
            'roles' => $business->roles()->exists(),
            'agreement' => $agreements->isNotEmpty(),
            'employees' => $employees->isNotEmpty(),
            'roles_assigned' => $employees->where('status', EmployeeStatus::Accepted)->whereNull('business_role_id')->isEmpty(),
        ];

        $person = fn (Employee $employee): array => [
            'id' => $employee->id,
            'name' => $employee->user->name,
            'slug' => $employee->user->slug,
            'avatar' => $employee->user->avatarUrl(),
            'role' => $employee->role?->name,
            'level' => $employee->role?->level,
            'is_live' => $employee->is_live,
        ];

        return [
            'setup' => $setup,
            'setup_done' => $setup['roles'] && $setup['agreement'] && filled($agreements->first()?->role_shares) && $setup['employees'],
            'tip_profiles' => $employees
                ->filter(fn (Employee $employee): bool => (bool) $employee->role?->receives_tips)
                ->sortByDesc('id')->take(5)->map($person)->values(),
            'employees' => $employees
                ->sortBy(fn (Employee $employee): int => $employee->role?->level ?? PHP_INT_MAX)
                ->take(5)->map($person)->values(),
            'reviews' => $business->tips()
                ->countsAsFeedback()
                ->whereNotNull('review')
                ->where('review', '!=', '')
                ->latest('id')
                ->limit(5)
                ->get(['id', 'rating', 'review', 'reviewer_name', 'created_at'])
                ->map(fn (Tip $tip): array => [
                    'id' => $tip->id,
                    'rating' => $tip->rating,
                    'review' => $tip->review,
                    'reviewer_name' => $tip->reviewer_name,
                    'at' => $tip->created_at?->toIso8601String(),
                ]),
        ];
    }

    private function reviewsBetween(User $user, CarbonImmutable $from, CarbonImmutable $until): int
    {
        return $this->feedback($user)
            ->whereNotNull('rating')
            ->whereBetween('created_at', [$from->utc(), $until->utc()->subSecond()])
            ->count();
    }

    /**
     * Paid tips per week for the last 8 weeks, oldest first.
     *
     * @return list<array{week_start: string, amount: string}>
     */
    private function weekly(User $user, CarbonImmutable $today): array
    {
        $weeks = [];
        for ($start = $today->startOfWeek()->subWeeks(7); $start <= $today; $start = $start->addWeek()) {
            $weeks[] = ['week_start' => $start->toDateString(), 'amount' => $this->paidBetween($user, $start, $start->addWeek())['amount']];
        }

        return $weeks;
    }

    /**
     * @return array{amount: string, count: int}
     */
    private function paidBetween(User $user, CarbonImmutable $from, CarbonImmutable $until): array
    {
        $totals = $this->paidTips($user)
            ->whereBetween('paid_at', [$from->utc(), $until->utc()->subSecond()])
            ->selectRaw('COALESCE(SUM(amount), 0) as amount, COUNT(*) as count')
            ->first();

        return ['amount' => $this->money((string) $totals->amount), 'count' => (int) $totals->count];
    }

    /**
     * Paid tips per day for the last 7 days, oldest first.
     *
     * @return list<array{date: string, amount: string, count: int}>
     */
    private function trend(User $user, CarbonImmutable $today): array
    {
        $from = $today->subDays(6);
        $byDay = $this->paidTips($user)
            ->where('paid_at', '>=', $from->utc())
            ->get(['amount', 'paid_at'])
            ->groupBy(fn (Tip $tip): string => $tip->paid_at->setTimezone($today->timezone)->toDateString());

        $days = [];
        for ($day = $from; $day <= $today; $day = $day->addDay()) {
            $tips = $byDay->get($day->toDateString(), collect());
            $days[] = [
                'date' => $day->toDateString(),
                'amount' => $this->money((string) $tips->sum('amount')),
                'count' => $tips->count(),
            ];
        }

        return $days;
    }

    /**
     * @return array{average: float, count: int, distribution: array<int, int>}
     */
    private function rating(User $user): array
    {
        $counts = $this->feedback($user)
            ->whereNotNull('rating')
            ->selectRaw('rating, COUNT(*) as total')
            ->groupBy('rating')
            ->pluck('total', 'rating');

        $count = (int) $counts->sum();
        $distribution = [];
        foreach (range(1, 5) as $stars) {
            $distribution[$stars] = (int) ($counts[$stars] ?? 0);
        }

        $average = $count === 0 ? 0 : collect($distribution)->map(fn (int $total, int $stars): int => $total * $stars)->sum() / $count;

        return ['average' => round($average, 1), 'count' => $count, 'distribution' => $distribution];
    }

    /**
     * The latest paid tips and reviews.
     *
     * @return list<array<string, mixed>>
     */
    private function recent(User $user): array
    {
        return $this->feedback($user)
            ->with('spot:id,name')
            ->latest('id')
            ->limit(5)
            ->get()
            ->map(fn (Tip $tip): array => [
                'id' => $tip->id,
                'type' => $tip->type,
                'amount' => $tip->amount,
                'rating' => $tip->rating,
                'review' => $tip->review,
                'reviewer_name' => $tip->reviewer_name,
                'spot' => $tip->spot?->name,
                'at' => ($tip->paid_at ?? $tip->created_at)?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return Builder<Tip>
     */
    private function paidTips(User $user): Builder
    {
        return $this->tips($user)->paid();
    }

    /**
     * @return Builder<Tip>
     */
    private function feedback(User $user): Builder
    {
        return $this->tips($user)->countsAsFeedback();
    }

    /**
     * A business owner sees the figures of the whole business, as in the old
     * app; everyone else sees the tips they received.
     *
     * @return Builder<Tip>
     */
    private function tips(User $user): Builder
    {
        $business = $user->business;

        return $business === null
            ? Tip::query()->whereBelongsTo($user, 'recipient')
            : Tip::query()->whereBelongsTo($business);
    }

    private function money(string $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
