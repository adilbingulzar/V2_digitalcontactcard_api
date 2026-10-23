<?php

namespace Tests\Feature\Http\Controllers\Api\V1\Business;

use App\Models\Business;
use App\Models\BusinessRole;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RoleControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_owner_creates_renames_and_deletes_a_role(): void
    {
        $business = Business::factory()->create();
        Sanctum::actingAs($business->owner);

        $id = $this->postJson(route('api.v1.me.business.roles.store'), ['name' => 'Bar', 'receives_tips' => true])
            ->assertCreated()
            ->json('data.id');

        $this->putJson(route('api.v1.me.business.roles.update', $id), ['name' => 'Bartender', 'receives_tips' => false])
            ->assertOk()
            ->assertJsonPath('data.name', 'Bartender');

        $this->deleteJson(route('api.v1.me.business.roles.destroy', $id))->assertNoContent();
        $this->assertSame(0, BusinessRole::count());
    }

    public function test_a_role_can_have_a_level_and_a_parent_role_of_the_same_business(): void
    {
        $business = Business::factory()->create();
        $manager = BusinessRole::factory()->for($business)->create(['name' => 'Manager', 'level' => 1]);
        $foreign = BusinessRole::factory()->create();
        Sanctum::actingAs($business->owner);

        $this->postJson(route('api.v1.me.business.roles.store'), ['name' => 'Waiter', 'receives_tips' => true, 'level' => 3, 'parent_id' => $manager->id])
            ->assertCreated()
            ->assertJsonPath('data.parent_id', $manager->id)
            ->assertJsonPath('data.level', 3);

        $this->postJson(route('api.v1.me.business.roles.store'), ['name' => 'Chef', 'receives_tips' => true, 'parent_id' => $foreign->id])
            ->assertJsonValidationErrors('parent_id');
        $this->putJson(route('api.v1.me.business.roles.update', $manager), ['name' => 'Manager', 'receives_tips' => false, 'parent_id' => $manager->id])
            ->assertJsonValidationErrors('parent_id');
    }

    public function test_returns_404_for_a_role_of_another_business(): void
    {
        $foreign = BusinessRole::factory()->create(['name' => 'Theirs']);
        Sanctum::actingAs(Business::factory()->create()->owner);

        $this->putJson(route('api.v1.me.business.roles.update', $foreign), ['name' => 'Mine', 'receives_tips' => true])->assertNotFound();
        $this->deleteJson(route('api.v1.me.business.roles.destroy', $foreign))->assertNotFound();

        $this->assertSame('Theirs', $foreign->refresh()->name);
    }

    public function test_role_names_are_unique_within_a_business(): void
    {
        $business = Business::factory()->create();
        BusinessRole::factory()->for($business)->create(['name' => 'Bar']);
        Sanctum::actingAs($business->owner);

        $this->postJson(route('api.v1.me.business.roles.store'), ['name' => 'Bar', 'receives_tips' => true])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name' => 'You already have a role with this name.']);
    }

    public function test_returns_403_for_a_user_without_a_business(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson(route('api.v1.me.business.roles.index'))->assertForbidden();
    }
}
