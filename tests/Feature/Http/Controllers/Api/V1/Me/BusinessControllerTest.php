<?php

namespace Tests\Feature\Http\Controllers\Api\V1\Me;

use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BusinessControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_owner_updates_the_business_details(): void
    {
        $business = Business::factory()->create();
        Sanctum::actingAs($business->owner);

        $this->patchJson(route('api.v1.me.business.update'), ['name' => 'Café Nieuw', 'email' => 'info@cafe.nl', 'address' => 'Dam 1'])
            ->assertOk()
            ->assertJsonPath('data.business.name', 'Café Nieuw');

        $this->assertSame('info@cafe.nl', $business->refresh()->email);
    }

    public function test_returns_403_for_a_user_without_a_business(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->patchJson(route('api.v1.me.business.update'), ['name' => 'Mine now'])->assertForbidden();
    }
}
