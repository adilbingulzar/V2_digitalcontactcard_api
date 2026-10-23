<?php

namespace Tests\Feature\Http\Controllers\Api\V1\Admin;

use App\Actions\Payouts\CalculatePayout;
use App\Actions\Tips\QuoteTip;
use App\Enums\PaymentStatus;
use App\Models\Subscription;
use App\Models\Tip;
use App\Models\User;
use App\Models\UserFee;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->admin()->create(['name' => 'Admin Person']));
    }

    public function test_searches_users_and_counts_only_paid_tips(): void
    {
        $anna = User::factory()->create(['name' => 'Anna Tipped']);
        Tip::factory()->for($anna, 'recipient')->create(['amount' => 10, 'payment_status' => PaymentStatus::Paid]);
        Tip::factory()->for($anna, 'recipient')->create(['amount' => 99, 'payment_status' => PaymentStatus::Cancelled]);
        User::factory()->create(['name' => 'Bob Other']);

        $this->getJson(route('api.v1.admin.users.index', ['q' => 'Anna']))
            ->assertOk()
            ->assertJsonPath('data.*.id', [$anna->id])
            ->assertJsonPath('data.0.tips_total', '10.00')
            ->assertJsonPath('data.0.tips_count', 1);
    }

    public function test_filters_users_by_whether_a_plan_is_running_today(): void
    {
        $subscribed = User::factory()->create(['name' => 'Has Plan']);
        Subscription::factory()->for($subscribed)->create([
            'plan' => 'free', 'pay_order_id' => null, 'is_active' => true,
            'starts_on' => today()->subDay(), 'ends_on' => today()->addMonth(),
        ]);
        $expired = User::factory()->create(['name' => 'Expired Plan']);
        Subscription::factory()->for($expired)->create([
            'plan' => 'free', 'pay_order_id' => null, 'is_active' => true,
            'starts_on' => today()->subYear(), 'ends_on' => today()->subDay(),
        ]);
        Sanctum::actingAs(User::factory()->admin()->create(['name' => 'Admin']));

        $this->getJson(route('api.v1.admin.users.index', ['subscribed' => 'yes']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Has Plan')
            ->assertJsonPath('data.0.plan_ends_on', today()->addMonth()->toDateString());

        $names = collect($this->getJson(route('api.v1.admin.users.index', ['subscribed' => 'no']))->json('data'))->pluck('name');
        $this->assertTrue($names->contains('Expired Plan'));
        $this->assertFalse($names->contains('Has Plan'));
    }

    public function test_own_fees_are_saved_used_and_removed(): void
    {
        $user = User::factory()->create();

        $this->putJson(route('api.v1.admin.users.fees', $user), [
            'tip' => ['percent' => 1.5, 'fixed' => 0.10],
            'payout' => ['manual' => ['percent' => 0, 'fixed' => 0]],
        ])->assertOk()->assertJsonPath('data.fees.tip.percent', '1.50')->assertJsonPath('data.fees.payout.manual.fixed', '0.00');

        $quote = app(QuoteTip::class)->handle($user, 1000, 10);
        $this->assertSame(25, $quote->platformFeeCents);
        $this->assertSame(0, app(CalculatePayout::class)->forBalance($user, 10000)['fee_cents']);

        $this->putJson(route('api.v1.admin.users.fees', $user), ['tip' => null, 'payout' => []])->assertOk()->assertJsonPath('data.fees.tip', null);
        $this->assertSame(0, UserFee::whereBelongsTo($user)->count());
    }
}
