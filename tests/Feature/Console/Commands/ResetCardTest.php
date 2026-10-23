<?php

namespace Tests\Feature\Console\Commands;

use App\Models\User;
use App\Models\Vcard;
use App\Models\VcardAssistantMessage;
use App\Models\VcardDraft;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ResetCardTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_removes_only_that_users_card_draft_and_chat(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $user = User::factory()->create(['email' => 'peter@tiqs.test', 'name' => 'Peter']);
        Vcard::factory()->for($user)->create();
        VcardDraft::factory()->for($user)->create();
        VcardAssistantMessage::factory()->for($user)->create();
        Storage::disk('public')->put("vcard-media/{$user->id}/logo.webp", 'x');
        Storage::disk('local')->put("assistant-attachments/{$user->id}/card.jpg", 'x');
        $other = User::factory()->create();
        Vcard::factory()->for($other)->create();

        $this->artisan('tiqs:reset-card', ['user' => 'Peter@tiqs.test', '--force' => true])
            ->expectsOutput('The card of Peter is reset. Open Card Settings to start again.')
            ->assertSuccessful();

        $user->refresh();
        $this->assertNull($user->vcard);
        $this->assertNull($user->vcardDraft);
        $this->assertSame(0, $user->vcardAssistantMessages()->count());
        $this->assertSame([], Storage::disk('public')->allFiles("vcard-media/{$user->id}"));
        $this->assertSame([], Storage::disk('local')->allFiles("assistant-attachments/{$user->id}"));
        $this->assertNotNull($other->fresh()->vcard);
        $this->assertNotNull($user->fresh(), 'The account itself stays.');
    }

    public function test_asks_which_account_when_an_email_address_is_shared(): void
    {
        User::factory()->count(2)->create(['email' => 'lia@example.com']);

        $this->artisan('tiqs:reset-card', ['user' => 'lia@example.com', '--force' => true])->assertFailed();
    }

    public function test_never_runs_in_production(): void
    {
        $this->app['env'] = 'production';
        $user = User::factory()->create();
        Vcard::factory()->for($user)->create();

        $this->artisan('tiqs:reset-card', ['user' => (string) $user->id, '--force' => true])
            ->expectsOutput('This command only runs outside production.')
            ->assertFailed();
        $this->assertNotNull($user->fresh()->vcard);
    }
}
