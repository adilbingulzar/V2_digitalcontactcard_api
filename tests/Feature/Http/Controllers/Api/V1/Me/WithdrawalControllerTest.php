<?php

namespace Tests\Feature\Http\Controllers\Api\V1\Me;

use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WithdrawalControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_lists_only_own_payout_requests_newest_first(): void
    {
        $user = User::factory()->create();
        $older = Withdrawal::factory()->paid()->for($user)->create();
        $newer = Withdrawal::factory()->for($user)->create();
        Withdrawal::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson(route('api.v1.me.withdrawals.index'))
            ->assertOk()
            ->assertJsonPath('data.*.id', [$newer->id, $older->id])
            ->assertJsonPath('data.1.status', 'paid');
    }
}
