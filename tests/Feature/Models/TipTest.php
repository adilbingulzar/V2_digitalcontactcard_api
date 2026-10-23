<?php

namespace Tests\Feature\Models;

use App\Enums\PaymentStatus;
use App\Models\Tip;
use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class TipTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_available_for_payout_returns_only_paid_tips_not_yet_withdrawn(): void
    {
        $recipient = User::factory()->create();
        $available = Tip::factory()->paid()->for($recipient, 'recipient')->create();
        Tip::factory()->for($recipient, 'recipient')->create();
        Tip::factory()->paid()->for($recipient, 'recipient')->create([
            'withdrawal_id' => Withdrawal::factory()->for($recipient)->create()->id,
        ]);

        $ids = $recipient->receivedTips()->availableForPayout()->pluck('id');

        $this->assertEquals([$available->id], $ids->all());
    }

    public function test_payment_fields_cannot_be_mass_assigned(): void
    {
        $tip = Tip::factory()->create();

        $tip->fill([
            'payment_status' => PaymentStatus::Paid,
            'paid_at' => now(),
            'pay_order_id' => 'forged-order',
            'withdrawal_id' => 1,
        ])->save();

        $tip->refresh();
        $this->assertSame(PaymentStatus::Pending, $tip->payment_status);
        $this->assertNull($tip->paid_at);
        $this->assertNull($tip->pay_order_id);
        $this->assertNull($tip->withdrawal_id);
    }

    public function test_amount_is_stored_as_exact_decimal(): void
    {
        $tip = Tip::factory()->create(['amount' => 0.1 + 0.2]);

        $this->assertSame('0.30', $tip->refresh()->amount);
    }
}
