<?php

namespace App\Actions\Tips;

use App\Enums\EmployeeStatus;
use App\Enums\UserStatus;
use App\Models\Agreement;
use App\Models\Employee;
use App\Models\Spot;
use App\Models\SpotEmployee;
use App\Models\User;
use App\Models\WorkingHour;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * Decides who receives a tip given to a person, and how much each receives.
 *
 * Rules (from the old app, without its rounding and double-fee bugs):
 * - A user who is on shift at a business, in a role that receives tips,
 *   shares the tip with the team on shift under their agreement: every other
 *   role's share is divided equally between its members on shift, and the
 *   recipient keeps the rest. Colleagues in the recipient's own role get nothing.
 * - On shift in a role without tips: only tipped individually, if the user
 *   accepts individual tips.
 * - Not on shift anywhere: the user keeps the whole tip.
 * - Only active (email-confirmed) users can be tipped.
 */
class SplitTip
{
    public function canReceiveTips(User $recipient, ?CarbonInterface $at = null): bool
    {
        if ($recipient->status !== UserStatus::Active || $recipient->trashed()) {
            return false;
        }

        $employment = $this->employmentOnShift($recipient, $this->localTime($at));

        if ($employment === null || $employment->role?->receives_tips) {
            return true;
        }

        return $recipient->accepts_individual_tips;
    }

    /**
     * The tip in cents per receiving user. The amounts always add up to $tipCents.
     *
     * @return array<int, array{business_id: ?int, amount_cents: int}> keyed by user id; the recipient comes first
     */
    public function split(User $recipient, int $tipCents, ?CarbonInterface $at = null): array
    {
        $at = $this->localTime($at);
        $employment = $this->employmentOnShift($recipient, $at);

        if ($employment === null || ! $employment->role?->receives_tips) {
            return [$recipient->id => ['business_id' => null, 'amount_cents' => $tipCents]];
        }

        $percentages = $this->teamPercentages($employment, $at);

        return $this->allocate($percentages, $tipCents, $employment->business_id);
    }

    /**
     * Tip given at a spot (table, bar). Shared by the spot's staff on shift under
     * the business's spot agreement: every role's share is divided equally among
     * its members, and shares of roles with nobody on shift go to those who are.
     * With nobody on shift, the business owner receives the tip.
     *
     * @return array<int, array{business_id: ?int, amount_cents: int}> keyed by user id
     */
    public function splitForSpot(Spot $spot, int $tipCents, ?CarbonInterface $at = null): array
    {
        $at = $this->localTime($at);
        $business = $spot->business;

        $team = SpotEmployee::query()
            ->whereBelongsTo($spot)
            ->where('is_active', true)
            ->whereNotNull('business_role_id')
            ->whereHas('user', fn ($query) => $query->where('status', UserStatus::Active))
            ->get()
            ->filter(fn (SpotEmployee $member): bool => $member->is_live || $this->hasShiftNow('spot', $member->user_id, $business->id, $spot->id, $at));

        if ($team->isEmpty()) {
            return [$business->owner_id => ['business_id' => $business->id, 'amount_cents' => $tipCents]];
        }

        $agreement = $business->agreements()->where('is_for_spots', true)->where('is_active', true)->latest('id')->first();
        $shares = $agreement?->role_shares ?? [];

        $weights = [];
        foreach ($team->groupBy('business_role_id') as $roleId => $members) {
            $roleShare = (float) ($shares[(string) $roleId] ?? 0);
            foreach ($members as $member) {
                $weights[$member->user_id] = $roleShare / $members->count();
            }
        }

        // No usable agreement: everyone on shift gets an equal part.
        if (array_sum($weights) <= 0) {
            $weights = array_fill_keys(array_keys($weights), 1.0);
        }

        $total = array_sum($weights);
        $percentages = array_map(fn (float $weight): float => $weight * 100 / $total, $weights);

        return $this->allocate($percentages, $tipCents, $business->id);
    }

    public function canReceiveSpotTips(Spot $spot): bool
    {
        return $spot->is_active
            && $spot->business->is_active
            && $spot->business->owner?->status === UserStatus::Active;
    }

    /**
     * Percent of the tip per user id, recipient first.
     *
     * @return array<int, float>
     */
    private function teamPercentages(Employee $employment, CarbonImmutable $at): array
    {
        $agreement = $employment->agreement ?? $employment->business->defaultAgreement;
        $shares = $agreement instanceof Agreement ? $agreement->role_shares : [];

        $others = $this->teamOnShift($employment, $at)
            ->reject(fn (Employee $member): bool => $member->user_id === $employment->user_id
                || $member->business_role_id === $employment->business_role_id);

        $perUser = [];
        foreach ($others->groupBy('business_role_id') as $roleId => $members) {
            $roleShare = (float) ($shares[(string) $roleId] ?? 0);

            foreach ($members as $member) {
                $perUser[$member->user_id] = $roleShare / $members->count();
            }
        }

        $othersTotal = array_sum($perUser);
        if ($othersTotal > 100) {
            $perUser = array_map(fn (float $percent): float => $percent * 100 / $othersTotal, $perUser);
            $othersTotal = 100;
        }

        return [$employment->user_id => 100 - $othersTotal] + $perUser;
    }

    /**
     * Largest remainder: whole cents per user that add up exactly to the total.
     *
     * @param  array<int, float>  $percentages
     * @return array<int, array{business_id: ?int, amount_cents: int}>
     */
    private function allocate(array $percentages, int $totalCents, ?int $businessId): array
    {
        $cents = [];
        $remainders = [];

        foreach ($percentages as $userId => $percent) {
            $exact = $totalCents * $percent / 100;
            $cents[$userId] = (int) floor($exact);
            $remainders[$userId] = $exact - $cents[$userId];
        }

        arsort($remainders);
        $left = $totalCents - array_sum($cents);
        foreach (array_keys($remainders) as $userId) {
            if ($left <= 0) {
                break;
            }
            $cents[$userId]++;
            $left--;
        }

        $result = [];
        foreach ($cents as $userId => $amount) {
            if ($amount > 0 || $userId === array_key_first($percentages)) {
                $result[$userId] = ['business_id' => $businessId, 'amount_cents' => $amount];
            }
        }

        return $result;
    }

    private function employmentOnShift(User $user, CarbonImmutable $at): ?Employee
    {
        return Employee::query()
            ->whereBelongsTo($user)
            ->where('status', EmployeeStatus::Accepted)
            ->with(['role', 'agreement', 'business.defaultAgreement'])
            ->latest('id')
            ->get()
            ->first(fn (Employee $employee): bool => $this->isOnShift($employee, $at));
    }

    /**
     * @return Collection<int, Employee>
     */
    private function teamOnShift(Employee $employment, CarbonImmutable $at): Collection
    {
        return Employee::query()
            ->where('business_id', $employment->business_id)
            ->where('status', EmployeeStatus::Accepted)
            ->whereNotNull('business_role_id')
            ->get()
            ->filter(fn (Employee $member): bool => $this->isOnShift($member, $at))
            ->values();
    }

    private function isOnShift(Employee $employee, CarbonImmutable $at): bool
    {
        return $employee->is_live || $this->hasShiftNow('shift', $employee->user_id, $employee->business_id, null, $at);
    }

    private function hasShiftNow(string $purpose, int $userId, int $businessId, ?int $spotId, CarbonImmutable $at): bool
    {
        $now = $at->format('H:i');
        $today = $at->format('D');
        $yesterday = $at->subDay()->format('D');

        return WorkingHour::query()
            ->where('purpose', $purpose)
            ->where('user_id', $userId)
            ->where('business_id', $businessId)
            ->when($spotId !== null, fn ($query) => $query->where('spot_id', $spotId))
            ->whereIn('day', [$today, $yesterday])
            ->where('is_active', true)
            ->get()
            ->contains(fn (WorkingHour $hours): bool => collect($hours->time_ranges)->contains(
                fn (array $range): bool => $this->coversTime(
                    $now,
                    (string) ($range['from'] ?? ''),
                    (string) ($range['to'] ?? ''),
                    isYesterday: $hours->day === $yesterday && $hours->day !== $today,
                ),
            ));
    }

    /**
     * A range whose end is before its start runs past midnight: "Fri 22:00-02:00"
     * covers Friday from 22:00 and Saturday until 02:00.
     */
    private function coversTime(string $time, string $from, string $to, bool $isYesterday): bool
    {
        if ($from === '' || $to === '') {
            return false;
        }

        $overnight = $from > $to;

        if ($isYesterday) {
            return $overnight && $time <= $to;
        }

        return $overnight ? $time >= $from : $time >= $from && $time <= $to;
    }

    private function localTime(?CarbonInterface $at): CarbonImmutable
    {
        return CarbonImmutable::instance($at ?? now())->setTimezone(config('tiqs.business_timezone'));
    }
}
