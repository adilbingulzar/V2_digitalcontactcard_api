<?php

namespace Tests\Feature\Http\Controllers\Api\V1\Business;

use App\Models\Agreement;
use App\Models\Business;
use App\Models\BusinessRole;
use App\Models\Employee;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AgreementControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_first_agreement_stores_shares_and_becomes_the_default(): void
    {
        [$business, $waiter, $kitchen] = $this->businessWithRoles();
        Sanctum::actingAs($business->owner);

        $this->postJson(route('api.v1.me.business.agreements.store'), $this->payload([[$waiter->id, 70], [$kitchen->id, 30]]))
            ->assertCreated()
            ->assertJsonPath('data.is_default', true)
            ->assertJsonPath('data.role_shares', [['role_id' => $waiter->id, 'percent' => 70], ['role_id' => $kitchen->id, 'percent' => 30]]);

        $agreement = Agreement::sole();
        $this->assertSame([(string) $waiter->id => 70, (string) $kitchen->id => 30], $agreement->role_shares);
        $this->assertSame($agreement->id, $business->refresh()->default_agreement_id);
    }

    public function test_the_list_counts_the_team_members_on_each_agreement(): void
    {
        $business = Business::factory()->create();
        $agreement = Agreement::factory()->for($business)->create();
        Employee::factory()->for($business)->count(2)->create(['agreement_id' => $agreement->id]);
        Employee::factory()->for($business)->pending()->create(['agreement_id' => $agreement->id]);
        Sanctum::actingAs($business->owner);

        $this->getJson(route('api.v1.me.business.agreements.index'))
            ->assertOk()
            ->assertJsonPath('data.0.employees', 2);
    }

    public function test_shares_must_add_up_to_100_percent(): void
    {
        [$business, $waiter, $kitchen] = $this->businessWithRoles();
        Sanctum::actingAs($business->owner);

        $this->postJson(route('api.v1.me.business.agreements.store'), $this->payload([[$waiter->id, 70], [$kitchen->id, 20]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['role_shares' => 'The shares must add up to 100%.']);
    }

    public function test_shares_can_only_use_roles_of_the_own_business(): void
    {
        [$business, $waiter] = $this->businessWithRoles();
        $foreignRole = BusinessRole::factory()->create();
        Sanctum::actingAs($business->owner);

        $this->postJson(route('api.v1.me.business.agreements.store'), $this->payload([[$waiter->id, 50], [$foreignRole->id, 50]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['role_shares' => 'Choose roles of your own business.']);
    }

    public function test_returns_404_for_an_agreement_of_another_business(): void
    {
        [$business, $waiter] = $this->businessWithRoles();
        $foreign = Agreement::factory()->create();
        Sanctum::actingAs($business->owner);

        $this->putJson(route('api.v1.me.business.agreements.update', $foreign), $this->payload([[$waiter->id, 100]]))->assertNotFound();
        $this->postJson(route('api.v1.me.business.agreements.default', $foreign))->assertNotFound();
    }

    public function test_owner_chooses_another_default_agreement(): void
    {
        [$business] = $this->businessWithRoles();
        $first = Agreement::factory()->for($business)->create();
        $second = Agreement::factory()->for($business)->create();
        $business->update(['default_agreement_id' => $first->id]);
        Sanctum::actingAs($business->owner);

        $this->postJson(route('api.v1.me.business.agreements.default', $second))->assertOk()->assertJsonPath('data.is_default', true);

        $this->assertSame($second->id, $business->refresh()->default_agreement_id);
    }

    /**
     * @return array{0: Business, 1: BusinessRole, 2: BusinessRole}
     */
    private function businessWithRoles(): array
    {
        $business = Business::factory()->create();

        return [$business, BusinessRole::factory()->for($business)->create(['name' => 'Waiter']), BusinessRole::factory()->for($business)->create(['name' => 'Kitchen'])];
    }

    /**
     * @param  list<array{0: int, 1: int|float}>  $shares
     * @return array<string, mixed>
     */
    private function payload(array $shares): array
    {
        return [
            'title' => 'Team tips',
            'content' => 'How we share tips.',
            'is_for_spots' => false,
            'role_shares' => array_map(fn (array $share): array => ['role_id' => $share[0], 'percent' => $share[1]], $shares),
        ];
    }
}
