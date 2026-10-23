<?php

namespace Tests\Feature\Http\Controllers\Api\V1\Public;

use App\Models\Spot;
use App\Models\VisitorLog;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class SpotControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_shows_the_spot_page_with_the_business_and_records_the_visit(): void
    {
        $spot = Spot::factory()->create(['name' => 'Terrace', 'slug' => 'terrace']);

        $response = $this->getJson(route('api.v1.public.spots.show', [$spot->business_id, 'terrace']));

        $response->assertOk()
            ->assertJsonPath('data.name', 'Terrace')
            ->assertJsonPath('data.business.id', $spot->business_id)
            ->assertJsonPath('data.can_receive_tips', true);
        $this->assertStringNotContainsString($spot->business->owner->email, $response->getContent());
        $this->assertSame('paytip_spot', VisitorLog::sole()->page_type);
    }

    public function test_returns_404_for_an_unknown_spot(): void
    {
        $spot = Spot::factory()->create();

        $this->getJson(route('api.v1.public.spots.show', [$spot->business_id, 'nope']))->assertNotFound();
    }
}
