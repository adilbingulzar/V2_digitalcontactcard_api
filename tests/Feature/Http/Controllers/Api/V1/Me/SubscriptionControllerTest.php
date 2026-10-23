<?php

namespace Tests\Feature\Http\Controllers\Api\V1\Me;

use App\Enums\PaymentStatus;
use App\Models\AppSetting;
use App\Models\BankAccount;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\Tip;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SubscriptionControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.paynl.base_url' => 'https://connect.pay.nl',
            'services.paynl.username' => 'AT-0000-0000',
            'services.paynl.password' => 'token',
            'services.paynl.service_id' => 'SL-0000-0000',
        ]);
        AppSetting::current()->update(['user_subscription_required' => true, 'free_trial_days' => 30, 'user_plan_monthly' => 5.55]);
    }

    public function test_new_accounts_are_in_the_free_trial_and_old_ones_need_a_plan(): void
    {
        $new = User::factory()->create();
        Sanctum::actingAs($new);
        $this->getJson(route('api.v1.me.subscription.show'))
            ->assertOk()
            ->assertJsonPath('data.required', true)
            ->assertJsonPath('data.allows_payout', true)
            ->assertJsonPath('data.trial_ends_on', $new->created_at->startOfDay()->addDays(30)->toDateString())
            ->assertJsonPath('data.prices.monthly', '5.55');

        $old = User::factory()->create(['created_at' => now()->subDays(40)]);
        BankAccount::factory()->for($old)->create();
        Tip::factory()->for($old, 'recipient')->create(['amount' => 50, 'payment_status' => PaymentStatus::Paid]);
        Sanctum::actingAs($old);

        $this->getJson(route('api.v1.me.payouts.show'))->assertOk()->assertJsonPath('data.can_request', false)->assertJsonPath('data.reason', 'Choose a plan to transfer your balance.');
        $this->postJson(route('api.v1.me.withdrawals.store'))->assertUnprocessable()->assertJsonValidationErrors(['payout' => 'Choose a plan to transfer your balance.']);
    }

    public function test_paying_for_a_plan_activates_it_only_when_pay_nl_confirms(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'connect.pay.nl/v1/orders' => Http::response(['id' => 'order-sub', 'status' => ['code' => 20], 'links' => ['redirect' => 'https://checkout.pay.nl/order-sub']], 201),
            'connect.pay.nl/v1/orders/order-sub/status' => Http::sequence()
                ->push(['id' => 'order-sub', 'status' => ['code' => 20, 'name' => 'x', 'action' => 'x']])
                ->push(['id' => 'order-sub', 'status' => ['code' => 100, 'name' => 'x', 'action' => 'x']]),
        ]);
        $user = User::factory()->create(['created_at' => now()->subDays(40)]);
        Sanctum::actingAs($user);

        $this->postJson(route('api.v1.me.subscription.checkout'), ['plan' => 'monthly', 'payment_method_id' => 10, 'amount' => '0.01'])
            ->assertCreated()
            ->assertJsonPath('data.redirect_url', 'https://checkout.pay.nl/order-sub');

        $payment = Payment::sole();
        $this->assertSame(['subscription', '5.55', '5.80'], [$payment->purpose, $payment->tip_amount, $payment->total_amount]);
        Http::assertSent(fn ($request): bool => $request->url() === 'https://connect.pay.nl/v1/orders' && $request['amount']['value'] === 580);

        $this->postJson(route('api.v1.webhooks.paynl'), ['object' => ['id' => 'order-sub']])->assertOk();
        $this->assertFalse(Subscription::sole()->is_active);

        $this->postJson(route('api.v1.webhooks.paynl'), ['object' => ['id' => 'order-sub']])->assertOk();
        $plan = Subscription::sole();
        $this->assertTrue($plan->is_active);
        $this->assertSame(PaymentStatus::Paid, $plan->payment_status);
        $this->assertSame(today()->addMonth()->subDay()->toDateString(), $plan->ends_on->toDateString());
        $this->getJson(route('api.v1.me.subscription.show'))->assertJsonPath('data.active', true)->assertJsonPath('data.allows_payout', true);
    }

    public function test_cannot_buy_a_plan_when_plans_are_off(): void
    {
        AppSetting::current()->update(['user_subscription_required' => false]);
        Sanctum::actingAs(User::factory()->create());

        $this->postJson(route('api.v1.me.subscription.checkout'), ['plan' => 'monthly', 'payment_method_id' => 10])->assertUnprocessable();
    }
}
