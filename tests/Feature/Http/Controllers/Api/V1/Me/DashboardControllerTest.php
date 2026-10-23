<?php

namespace Tests\Feature\Http\Controllers\Api\V1\Me;

use App\Models\BankAccount;
use App\Models\Business;
use App\Models\BusinessRole;
use App\Models\Employee;
use App\Models\Referral;
use App\Models\Tip;
use App\Models\User;
use App\Models\Withdrawal;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_shows_balance_totals_trend_and_rating_for_the_signed_in_user(): void
    {
        // Friday 9 October 2026, 12:00 in Amsterdam.
        $this->travelTo(CarbonImmutable::parse('2026-10-09 12:00:00', 'Europe/Amsterdam'));
        $user = User::factory()->create();
        BankAccount::factory()->for($user)->create();

        Tip::factory()->paid()->for($user, 'recipient')->create(['amount' => 5, 'paid_at' => now()->subHour(), 'rating' => 5]);
        Tip::factory()->paid()->for($user, 'recipient')->create(['amount' => 2.5, 'paid_at' => now()->subDay(), 'rating' => 3]);
        Tip::factory()->paid()->for($user, 'recipient')->create([
            'amount' => 10, 'paid_at' => now()->subDays(2), 'rating' => null,
            'withdrawal_id' => Withdrawal::factory()->for($user)->create()->id,
        ]);
        // Abandoned checkout: neither its amount nor its rating counts.
        Tip::factory()->for($user, 'recipient')->create(['amount' => 99, 'rating' => 1]);
        Tip::factory()->paid()->create(['amount' => 50]);
        Referral::factory()->create(['referrer_id' => $user->id, 'amount' => 5, 'is_confirmed' => true]);
        Sanctum::actingAs($user);

        $response = $this->getJson(route('api.v1.me.dashboard'));

        $response->assertOk()
            ->assertJsonPath('data.balance', ['tips' => '7.50', 'referrals' => '5.00', 'total' => '12.50'])
            ->assertJsonPath('data.totals.today', ['amount' => '5.00', 'count' => 1])
            ->assertJsonPath('data.totals.yesterday', ['amount' => '2.50', 'count' => 1])
            ->assertJsonPath('data.totals.last_7_days', ['amount' => '17.50', 'count' => 3])
            ->assertJsonPath('data.rating.count', 2)
            ->assertJsonPath('data.rating.average', 4)
            ->assertJsonPath('data.setup.bank_account', true)
            ->assertJsonPath('data.setup.payout_settings', false)
            ->assertJsonPath('data.setup_done', false)
            ->assertJsonPath('data.business', null)
            ->assertJsonCount(7, 'data.trend')
            ->assertJsonPath('data.trend.6', ['date' => '2026-10-09', 'amount' => '5.00', 'count' => 1]);
    }

    public function test_a_business_owner_sees_business_figures_setup_steps_and_staff(): void
    {
        $business = Business::factory()->create();
        $tipRole = BusinessRole::factory()->for($business)->create(['name' => 'Waiter', 'level' => 3, 'receives_tips' => true]);
        $adminRole = BusinessRole::factory()->for($business)->create(['name' => 'Manager', 'level' => 1, 'receives_tips' => false]);
        $waiter = Employee::factory()->for($business)->create(['business_role_id' => $tipRole->id, 'is_live' => true]);
        Employee::factory()->for($business)->create(['business_role_id' => $adminRole->id]);
        Employee::factory()->for($business)->create(['business_role_id' => null]);
        Tip::factory()->paid()->for($business)->create(['amount' => 8, 'paid_at' => now(), 'rating' => 4, 'review' => 'Great team']);
        Tip::factory()->paid()->create(['amount' => 50]);
        Sanctum::actingAs($business->owner);

        $this->getJson(route('api.v1.me.dashboard'))
            ->assertOk()
            ->assertJsonPath('data.totals.today', ['amount' => '8.00', 'count' => 1])
            ->assertJsonPath('data.reviews.today', 1)
            ->assertJsonPath('data.business.setup', ['roles' => true, 'agreement' => false, 'employees' => true, 'roles_assigned' => false])
            ->assertJsonPath('data.business.setup_done', false)
            ->assertJsonCount(1, 'data.business.tip_profiles')
            ->assertJsonPath('data.business.tip_profiles.0.id', $waiter->id)
            ->assertJsonPath('data.business.employees.0.role', 'Manager')
            ->assertJsonPath('data.business.employees.2.role', null)
            ->assertJsonPath('data.business.reviews.0.review', 'Great team');
    }

    public function test_returns_403_for_an_unconfirmed_account(): void
    {
        Sanctum::actingAs(User::factory()->unverified()->create());

        $this->getJson(route('api.v1.me.dashboard'))->assertForbidden();
    }

    public function test_returns_401_without_a_token(): void
    {
        $this->getJson(route('api.v1.me.dashboard'))->assertUnauthorized();
    }
}
