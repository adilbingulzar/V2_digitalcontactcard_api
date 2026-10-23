<?php

namespace Tests\Feature\Actions\Tips;

use App\Actions\Tips\SplitTip;
use App\Models\Agreement;
use App\Models\Business;
use App\Models\BusinessRole;
use App\Models\Employee;
use App\Models\Spot;
use App\Models\SpotEmployee;
use App\Models\User;
use App\Models\WorkingHour;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class SplitTipTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_user_not_on_shift_anywhere_keeps_the_whole_tip(): void
    {
        $user = User::factory()->create();

        $split = app(SplitTip::class)->split($user, 500);

        $this->assertSame([$user->id => ['business_id' => null, 'amount_cents' => 500]], $split);
    }

    public function test_tip_is_shared_with_the_team_on_shift_under_the_agreement(): void
    {
        [$business, $roles] = $this->businessWithRoles(['Waiter' => 50, 'Manager' => 20, 'Kitchen' => 30]);
        $waiter = $this->member($business, $roles['Waiter']);
        $manager = $this->member($business, $roles['Manager']);
        $cookA = $this->member($business, $roles['Kitchen']);
        $cookB = $this->member($business, $roles['Kitchen']);

        $split = app(SplitTip::class)->split($waiter, 1000);

        $this->assertSame(500, $split[$waiter->id]['amount_cents']);
        $this->assertSame(200, $split[$manager->id]['amount_cents']);
        $this->assertSame(150, $split[$cookA->id]['amount_cents']);
        $this->assertSame(150, $split[$cookB->id]['amount_cents']);
        $this->assertSame($business->id, $split[$waiter->id]['business_id']);
    }

    public function test_colleagues_in_the_recipients_own_role_get_nothing(): void
    {
        [$business, $roles] = $this->businessWithRoles(['Waiter' => 80, 'Manager' => 20]);
        $waiter = $this->member($business, $roles['Waiter']);
        $otherWaiter = $this->member($business, $roles['Waiter']);
        $this->member($business, $roles['Manager']);

        $split = app(SplitTip::class)->split($waiter, 1000);

        $this->assertSame(800, $split[$waiter->id]['amount_cents']);
        $this->assertArrayNotHasKey($otherWaiter->id, $split);
    }

    public function test_shares_always_add_up_to_the_tip_in_whole_cents(): void
    {
        [$business, $roles] = $this->businessWithRoles(['Waiter' => 0, 'Kitchen' => 100]);
        $waiter = $this->member($business, $roles['Waiter']);
        $this->member($business, $roles['Kitchen']);
        $this->member($business, $roles['Kitchen']);
        $this->member($business, $roles['Kitchen']);

        $split = app(SplitTip::class)->split($waiter, 1000);

        $this->assertSame(1000, array_sum(array_column($split, 'amount_cents')));
    }

    public function test_shift_hours_count_in_the_business_timezone(): void
    {
        [$business, $roles] = $this->businessWithRoles(['Waiter' => 50, 'Manager' => 50]);
        $waiter = $this->member($business, $roles['Waiter'], live: false);
        $this->onShift($waiter, $business, 'Fri', '17:00', '23:00');
        $manager = $this->member($business, $roles['Manager'], live: false);
        $this->onShift($manager, $business, 'Fri', '17:00', '23:00');

        // Friday 18:30 in Amsterdam (summer time) is 16:30 UTC.
        $during = CarbonImmutable::parse('2026-10-09 16:30:00', 'UTC');
        $before = CarbonImmutable::parse('2026-10-09 13:00:00', 'UTC');

        $this->assertSame(500, app(SplitTip::class)->split($waiter, 1000, $during)[$manager->id]['amount_cents']);
        $this->assertSame([$waiter->id => ['business_id' => null, 'amount_cents' => 1000]], app(SplitTip::class)->split($waiter, 1000, $before));
    }

    public function test_friday_night_shift_running_past_midnight_still_counts_on_saturday_morning(): void
    {
        [$business, $roles] = $this->businessWithRoles(['Waiter' => 100]);
        $waiter = $this->member($business, $roles['Waiter'], live: false);
        $this->onShift($waiter, $business, 'Fri', '22:00', '02:00');

        $saturdayNight = CarbonImmutable::parse('2026-10-10 01:30:00', 'Europe/Amsterdam');
        $saturdayAfternoon = CarbonImmutable::parse('2026-10-10 15:00:00', 'Europe/Amsterdam');

        $this->assertSame($business->id, app(SplitTip::class)->split($waiter, 1000, $saturdayNight)[$waiter->id]['business_id']);
        $this->assertNull(app(SplitTip::class)->split($waiter, 1000, $saturdayAfternoon)[$waiter->id]['business_id']);
    }

    public function test_spot_tip_is_shared_by_role_among_the_spot_staff_on_shift(): void
    {
        [$business, $roles] = $this->businessWithRoles(['Waiter' => 60, 'Bar' => 40], forSpots: true);
        $spot = Spot::factory()->for($business)->create();
        $waiterA = $this->spotMember($spot, $roles['Waiter']);
        $waiterB = $this->spotMember($spot, $roles['Waiter']);
        $bartender = $this->spotMember($spot, $roles['Bar']);

        $split = app(SplitTip::class)->splitForSpot($spot, 1000);

        $this->assertSame(300, $split[$waiterA->id]['amount_cents']);
        $this->assertSame(300, $split[$waiterB->id]['amount_cents']);
        $this->assertSame(400, $split[$bartender->id]['amount_cents']);
    }

    public function test_spot_shares_of_roles_with_nobody_on_shift_go_to_those_who_are(): void
    {
        [$business, $roles] = $this->businessWithRoles(['Waiter' => 60, 'Bar' => 40], forSpots: true);
        $spot = Spot::factory()->for($business)->create();
        $waiter = $this->spotMember($spot, $roles['Waiter']);
        $this->spotMember($spot, $roles['Bar'], live: false);

        $this->assertSame([$waiter->id => ['business_id' => $business->id, 'amount_cents' => 1000]], app(SplitTip::class)->splitForSpot($spot, 1000));
    }

    public function test_spot_tip_goes_to_the_business_owner_when_nobody_is_on_shift(): void
    {
        $spot = Spot::factory()->create();

        $split = app(SplitTip::class)->splitForSpot($spot, 700);

        $this->assertSame([$spot->business->owner_id => ['business_id' => $spot->business_id, 'amount_cents' => 700]], $split);
    }

    public function test_pending_account_cannot_receive_tips(): void
    {
        $this->assertFalse(app(SplitTip::class)->canReceiveTips(User::factory()->unverified()->create()));
    }

    public function test_on_shift_in_a_role_without_tips_only_receives_tips_when_accepting_individual_tips(): void
    {
        $business = Business::factory()->create();
        $role = BusinessRole::factory()->for($business)->create(['receives_tips' => false]);
        $declines = $this->member($business, $role, attributes: ['accepts_individual_tips' => false]);
        $accepts = $this->member($business, $role, attributes: ['accepts_individual_tips' => true]);

        $this->assertFalse(app(SplitTip::class)->canReceiveTips($declines));
        $this->assertTrue(app(SplitTip::class)->canReceiveTips($accepts));
        $this->assertSame([$accepts->id => ['business_id' => null, 'amount_cents' => 300]], app(SplitTip::class)->split($accepts, 300));
    }

    /**
     * @param  array<string, int>  $shares  role name => percent
     * @return array{0: Business, 1: array<string, BusinessRole>}
     */
    private function businessWithRoles(array $shares, bool $forSpots = false): array
    {
        $business = Business::factory()->create();
        $roles = [];
        foreach (array_keys($shares) as $name) {
            $roles[$name] = BusinessRole::factory()->for($business)->create(['name' => $name, 'receives_tips' => true]);
        }

        $agreement = Agreement::factory()->for($business)->create([
            'role_shares' => collect($shares)->mapWithKeys(fn (int $percent, string $name): array => [(string) $roles[$name]->id => $percent])->all(),
            'is_for_spots' => $forSpots,
        ]);
        $business->update(['default_agreement_id' => $agreement->id]);

        return [$business, $roles];
    }

    private function spotMember(Spot $spot, BusinessRole $role, bool $live = true): User
    {
        $user = User::factory()->create();
        SpotEmployee::factory()->for($spot)->for($user)->create(['business_role_id' => $role->id, 'is_live' => $live]);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function member(Business $business, BusinessRole $role, bool $live = true, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        Employee::factory()->for($business)->for($user)->create(['business_role_id' => $role->id, 'is_live' => $live]);

        return $user;
    }

    private function onShift(User $user, Business $business, string $day, string $from, string $to): void
    {
        WorkingHour::factory()->for($user)->create([
            'purpose' => 'shift',
            'business_id' => $business->id,
            'day' => $day,
            'time_ranges' => [['from' => $from, 'to' => $to]],
        ]);
    }
}
