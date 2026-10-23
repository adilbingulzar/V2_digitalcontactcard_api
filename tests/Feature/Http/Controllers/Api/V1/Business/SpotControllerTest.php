<?php

namespace Tests\Feature\Http\Controllers\Api\V1\Business;

use App\Models\Business;
use App\Models\Spot;
use App\Models\Tip;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SpotControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_creates_numbered_spots_in_one_go_with_free_addresses(): void
    {
        $business = Business::factory()->create();
        Spot::factory()->for($business)->create(['name' => 'Table 2', 'slug' => 'table-2']);
        Sanctum::actingAs($business->owner);

        $this->postJson(route('api.v1.me.business.spots.bulk'), ['name' => 'Table', 'count' => 3, 'space' => true])
            ->assertCreated()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.name', 'Table 1')
            ->assertJsonPath('data.1.slug', 'table-2-2');

        $this->postJson(route('api.v1.me.business.spots.bulk'), ['name' => 'Bar', 'count' => 2, 'space' => false])
            ->assertJsonPath('data.1.name', 'Bar2')
            ->assertJsonPath('data.1.slug', 'bar2');

        $this->postJson(route('api.v1.me.business.spots.bulk'), ['name' => 'Too many', 'count' => 101, 'space' => false])
            ->assertJsonValidationErrors('count');
        $this->assertSame(6, $business->spots()->count());
    }

    public function test_creates_a_spot_with_a_url_name_from_its_name(): void
    {
        $business = Business::factory()->create();
        Sanctum::actingAs($business->owner);

        $this->postJson(route('api.v1.me.business.spots.store'), ['name' => 'Table 12', 'is_active' => true])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'table-12')
            ->assertJsonPath('data.url', config('tiqs.frontend_url')."/paytips/spot/{$business->id}/table-12");
    }

    public function test_url_names_are_unique_within_the_business(): void
    {
        $business = Business::factory()->create();
        Spot::factory()->for($business)->create(['slug' => 'bar']);
        Spot::factory()->create(['slug' => 'terrace']);
        Sanctum::actingAs($business->owner);

        $this->postJson(route('api.v1.me.business.spots.store'), ['name' => 'Bar', 'is_active' => true])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['slug' => 'Another spot already uses this URL name.']);
        $this->postJson(route('api.v1.me.business.spots.store'), ['name' => 'Terrace', 'is_active' => true])->assertCreated();
    }

    public function test_deleting_a_spot_keeps_its_tips(): void
    {
        $spot = Spot::factory()->create();
        $tip = Tip::factory()->paid()->for($spot)->create(['business_id' => $spot->business_id]);
        Sanctum::actingAs($spot->business->owner);

        $this->deleteJson(route('api.v1.me.business.spots.destroy', $spot))->assertNoContent();

        $this->assertModelMissing($spot);
        $this->assertNull($tip->refresh()->spot_id);
    }

    public function test_returns_404_for_a_spot_of_another_business(): void
    {
        $foreign = Spot::factory()->create();
        Sanctum::actingAs(Business::factory()->create()->owner);

        $this->putJson(route('api.v1.me.business.spots.update', $foreign), ['name' => 'Mine', 'is_active' => true])->assertNotFound();
        $this->deleteJson(route('api.v1.me.business.spots.destroy', $foreign))->assertNotFound();
    }
}
