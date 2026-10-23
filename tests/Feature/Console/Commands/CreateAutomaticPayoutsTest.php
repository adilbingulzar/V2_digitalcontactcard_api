<?php

namespace Tests\Feature\Console\Commands;

use App\Models\BankAccount;
use App\Models\Tip;
use App\Models\User;
use App\Models\UserSetting;
use App\Models\Withdrawal;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CreateAutomaticPayoutsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_requests_payouts_for_weekly_users_on_monday_only(): void
    {
        $weekly = $this->userOnSchedule('weekly');
        $manual = $this->userOnSchedule(null);

        $this->travelTo(CarbonImmutable::parse('2026-10-13 06:00', 'Europe/Amsterdam'));
        $this->artisan('tiqs:automatic-payouts')->assertSuccessful();
        $this->assertSame(0, Withdrawal::count());

        $this->travelTo(CarbonImmutable::parse('2026-10-12 06:00', 'Europe/Amsterdam'));
        $this->artisan('tiqs:automatic-payouts')->assertSuccessful();

        $this->assertSame([$weekly->id], Withdrawal::pluck('user_id')->all());
        $this->assertSame(0, Withdrawal::whereBelongsTo($manual)->count());
    }

    public function test_skips_users_without_a_bank_account(): void
    {
        $user = $this->userOnSchedule('daily');
        $user->bankAccount()->delete();

        $this->artisan('tiqs:automatic-payouts')->assertSuccessful();

        $this->assertSame(0, Withdrawal::count());
    }

    private function userOnSchedule(?string $schedule): User
    {
        $user = User::factory()->create();
        BankAccount::factory()->for($user)->create();
        Tip::factory()->paid()->for($user, 'recipient')->create(['amount' => 25]);
        if ($schedule !== null) {
            UserSetting::factory()->for($user)->create(['key' => 'payout', 'value' => 'auto', 'sub_value' => $schedule]);
        }

        return $user;
    }
}
