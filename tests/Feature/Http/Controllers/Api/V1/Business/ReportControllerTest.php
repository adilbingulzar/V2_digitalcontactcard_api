<?php

namespace Tests\Feature\Http\Controllers\Api\V1\Business;

use App\Models\Business;
use App\Models\Spot;
use App\Models\Tip;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_totals_paid_tips_per_member_and_per_spot(): void
    {
        $business = Business::factory()->create();
        $spot = Spot::factory()->for($business)->create(['name' => 'Bar']);
        $anna = User::factory()->create(['name' => 'Anna']);
        $ben = User::factory()->create(['name' => 'Ben']);
        Tip::factory()->paid()->for($anna, 'recipient')->create(['business_id' => $business->id, 'spot_id' => $spot->id, 'amount' => 6]);
        Tip::factory()->paid()->for($anna, 'recipient')->create(['business_id' => $business->id, 'amount' => 4]);
        Tip::factory()->paid()->for($ben, 'recipient')->create(['business_id' => $business->id, 'spot_id' => $spot->id, 'amount' => 3]);
        Tip::factory()->for($ben, 'recipient')->create(['business_id' => $business->id, 'amount' => 50]);
        Tip::factory()->paid()->for($ben, 'recipient')->create(['amount' => 80]);
        Sanctum::actingAs($business->owner);

        $this->getJson(route('api.v1.me.business.report', ['period' => 'all']))
            ->assertOk()
            ->assertJsonPath('data.total', ['amount' => '13.00', 'count' => 3])
            ->assertJsonPath('data.per_member', [
                ['user_id' => $anna->id, 'name' => 'Anna', 'amount' => '10.00', 'count' => 2],
                ['user_id' => $ben->id, 'name' => 'Ben', 'amount' => '3.00', 'count' => 1],
            ])
            ->assertJsonPath('data.per_spot', [['spot_id' => $spot->id, 'name' => 'Bar', 'amount' => '9.00', 'count' => 2]]);
    }
}
