<?php

namespace Tests\Feature\Http\Controllers\Api\V1\Me;

use App\Models\User;
use App\Models\VcardDraft;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VcardDraftControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_keeps_unpublished_changes_until_they_are_discarded(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson(route('api.v1.me.vcard-draft.show'))->assertOk()->assertJsonPath('data', null);

        $this->putJson(route('api.v1.me.vcard-draft.update'), ['draft' => [
            'name' => 'Peter Roos',
            'template' => 'bold',
            'show_review' => false,
            'social_links' => ['https://tiqs.com'],
            'design' => ['theme' => ['accent' => '#19849A'], 'blocks' => [['type' => 'header', 'layout' => 'side', 'onclick' => 'x'], ['type' => 'nonsense']]],
        ]])
            ->assertOk()
            ->assertJsonPath('data.design.theme.accent', '#19849a')
            ->assertJsonPath('data.design.blocks.0.layout', 'side')
            ->assertJsonMissingPath('data.design.blocks.0.onclick')
            ->assertJsonMissingPath('data.design.blocks.1');

        $this->getJson(route('api.v1.me.vcard-draft.show'))
            ->assertJsonPath('data.name', 'Peter Roos')
            ->assertJsonPath('data.show_review', false)
            ->assertJsonPath('data.social_links', ['https://tiqs.com']);

        $this->deleteJson(route('api.v1.me.vcard-draft.destroy'))->assertNoContent();
        $this->assertNull($user->vcardDraft()->first());
    }

    public function test_refuses_unknown_fields(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->putJson(route('api.v1.me.vcard-draft.update'), ['draft' => ['role' => 'admin']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('draft');
    }

    public function test_publishing_the_card_removes_the_draft_and_only_the_users_own(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        VcardDraft::factory()->for($user)->create();
        $other = VcardDraft::factory()->create();

        $this->putJson(route('api.v1.me.vcard.update'), [
            'is_active' => true, 'theme' => 2, 'show_business_name' => true, 'business_name' => 'tiqs', 'business_email' => 'hi@tiqs.test',
            'show_location' => false, 'show_opening_hours' => false, 'show_review' => true, 'show_business_icon' => true, 'show_email_icon' => true,
            'show_phone_icon' => true, 'show_address_icon' => true,
        ])->assertOk();

        $this->assertNull($user->vcardDraft()->first());
        $this->assertNotNull($other->fresh());
    }
}
