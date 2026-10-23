<?php

namespace Tests\Feature\Http\Controllers\Api\V1\Me;

use App\Enums\WithdrawalStatus;
use App\Mail\PayoutRequestedMail;
use App\Models\BankAccount;
use App\Models\Referral;
use App\Models\Spot;
use App\Models\Tip;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\Withdrawal;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PayoutControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_overview_shows_balance_fee_and_net_amount_for_the_schedule(): void
    {
        $user = $this->userWithBalance(['20.00']);
        Sanctum::actingAs($user);

        $this->getJson(route('api.v1.me.payouts.show'))
            ->assertOk()
            ->assertJsonPath('data.balance', '20.00')
            ->assertJsonPath('data.fee', '0.10')
            ->assertJsonPath('data.net', '19.90')
            ->assertJsonPath('data.can_request', true)
            ->assertJsonPath('data.settings.schedule', 'manual')
            ->assertJsonPath('data.bank_account.account_number', '••••••••••••••4300');
    }

    public function test_overview_splits_the_balance_and_lists_recent_transfers_and_rewards(): void
    {
        $user = $this->userWithBalance(['20.00']);
        Tip::factory()->paid()->for($user, 'recipient')->for(Spot::factory(), 'spot')->create(['amount' => 4]);
        Referral::factory()->create(['referrer_id' => $user->id, 'amount' => 2.5, 'is_confirmed' => true]);
        Withdrawal::factory()->for($user)->create(['amount' => 7, 'status' => WithdrawalStatus::Requested]);
        Sanctum::actingAs($user);

        $this->getJson(route('api.v1.me.payouts.show'))
            ->assertOk()
            ->assertJsonPath('data.breakdown', ['tips' => '20.00', 'spots' => '4.00', 'referrals' => '2.50'])
            ->assertJsonPath('data.processing', '7.00')
            ->assertJsonCount(1, 'data.recent_withdrawals')
            ->assertJsonPath('data.recent_referrals.0.amount', '2.50');
    }

    public function test_request_pays_out_paid_tips_and_rewards_but_never_unpaid_tips(): void
    {
        Mail::fake();
        config(['tiqs.payouts.notify_email' => 'payouts@example.com']);
        $user = $this->userWithBalance(['10.00', '5.00']);
        $unpaid = Tip::factory()->for($user, 'recipient')->create(['amount' => 99]);
        $reward = Referral::factory()->create(['referrer_id' => $user->id, 'amount' => 5, 'is_confirmed' => true]);
        Sanctum::actingAs($user);

        $response = $this->postJson(route('api.v1.me.withdrawals.store'));

        $response->assertCreated()->assertJsonPath('data.amount', '19.90')->assertJsonPath('data.fee', '0.10');
        $withdrawal = Withdrawal::sole();
        $this->assertSame(WithdrawalStatus::Requested, $withdrawal->status);
        $this->assertSame(2, $withdrawal->tips()->count());
        $this->assertNull($unpaid->refresh()->withdrawal_id);
        $this->assertSame($withdrawal->id, $reward->refresh()->withdrawal_id);
        $this->assertSame(1, UserNotification::whereBelongsTo($user)->where('type', 'payment_processing')->count());
        Mail::assertQueued(PayoutRequestedMail::class, fn (PayoutRequestedMail $mail): bool => $mail->hasTo('payouts@example.com'));
    }

    public function test_the_same_balance_cannot_be_paid_out_twice(): void
    {
        $user = $this->userWithBalance(['10.00']);
        Sanctum::actingAs($user);

        $this->postJson(route('api.v1.me.withdrawals.store'))->assertCreated();
        $this->postJson(route('api.v1.me.withdrawals.store'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['payout']);

        $this->assertSame(1, Withdrawal::count());
    }

    public function test_requires_a_bank_account(): void
    {
        $user = User::factory()->create();
        Tip::factory()->paid()->for($user, 'recipient')->create(['amount' => 10]);
        Sanctum::actingAs($user);

        $this->postJson(route('api.v1.me.withdrawals.store'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['payout' => 'Add your bank account before requesting a payout.']);
    }

    public function test_refuses_a_payout_below_the_minimum(): void
    {
        $user = $this->userWithBalance(['0.80']);
        Sanctum::actingAs($user);

        $this->postJson(route('api.v1.me.withdrawals.store'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['payout' => 'The minimum payout is € 1,00 after fees.']);
    }

    /**
     * @param  list<string>  $amounts  paid tips
     */
    private function userWithBalance(array $amounts): User
    {
        $user = User::factory()->create();
        BankAccount::factory()->for($user)->create(['account_number' => 'NL91ABNA0417164300']);
        foreach ($amounts as $amount) {
            Tip::factory()->paid()->for($user, 'recipient')->create(['amount' => $amount]);
        }

        return $user;
    }
}
