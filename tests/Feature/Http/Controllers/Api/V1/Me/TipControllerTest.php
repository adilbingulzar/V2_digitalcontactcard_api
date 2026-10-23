<?php

namespace Tests\Feature\Http\Controllers\Api\V1\Me;

use App\Models\Tip;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TipControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_lists_own_paid_tips_and_reviews_of_the_period_with_totals(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-09 12:00:00', 'Europe/Amsterdam'));
        $user = User::factory()->create();
        $today = Tip::factory()->paid()->for($user, 'recipient')->create(['amount' => 4, 'paid_at' => now()]);
        $review = Tip::factory()->rating()->for($user, 'recipient')->create(['created_at' => now()]);
        Tip::factory()->paid()->for($user, 'recipient')->create(['amount' => 9, 'paid_at' => now()->subDays(2)]);
        Tip::factory()->for($user, 'recipient')->create(['amount' => 50]);
        Tip::factory()->paid()->create(['amount' => 70, 'paid_at' => now()]);
        Sanctum::actingAs($user);

        $response = $this->getJson(route('api.v1.me.tips.index', ['period' => 'today']));

        $response->assertOk()
            ->assertJsonPath('summary', ['paid_amount' => '4.00', 'paid_count' => 1])
            ->assertJsonPath('data.*.id', [$review->id, $today->id]);
        $this->assertArrayNotHasKey('reviewer_email', $response->json('data.0'));
    }

    public function test_status_filter_shows_cancelled_checkouts(): void
    {
        $user = User::factory()->create();
        $cancelled = Tip::factory()->for($user, 'recipient')->create(['payment_status' => 'cancelled']);
        Sanctum::actingAs($user);

        $this->getJson(route('api.v1.me.tips.index', ['period' => 'all', 'status' => 'cancelled']))
            ->assertOk()
            ->assertJsonPath('data.*.id', [$cancelled->id]);
    }

    public function test_custom_period_needs_both_dates_in_order(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson(route('api.v1.me.tips.index', ['period' => 'custom', 'from' => '2026-10-09', 'until' => '2026-10-01']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['until']);
    }

    public function test_returns_401_without_a_token(): void
    {
        $this->getJson(route('api.v1.me.tips.index'))->assertUnauthorized();
    }
}
