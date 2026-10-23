<?php

namespace Tests\Feature\Http\Controllers\Api\V1\Me;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VcardMediaControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_stores_a_picture_as_webp_in_the_users_folder_and_saves_it_in_the_design(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $path = $this->post(route('api.v1.me.vcard-media.store'), ['image' => UploadedFile::fake()->image('photo.jpg', 800, 600)], ['Accept' => 'application/json'])
            ->assertCreated()
            ->json('data.path');

        $this->assertMatchesRegularExpression("#^vcard-media/{$user->id}/[0-9a-f-]+\\.webp$#", $path);
        Storage::disk('public')->assertExists($path);

        $this->putJson(route('api.v1.me.vcard.update'), [
            'is_active' => true, 'theme' => 2, 'show_business_name' => true, 'business_name' => 'Cafe Anna', 'business_email' => 'hi@anna.test',
            'show_location' => false, 'show_opening_hours' => false, 'show_review' => true, 'show_business_icon' => true, 'show_email_icon' => true,
            'show_phone_icon' => true, 'show_address_icon' => true,
            'design' => ['theme' => ['accent' => '#FBBF24'], 'blocks' => [['type' => 'image', 'url' => Storage::disk('public')->url($path)], ['type' => 'header', 'logo' => 'https://evil.example.com/x.png']]],
        ])
            ->assertOk()
            ->assertJsonPath('data.design.theme.accent', '#fbbf24')
            ->assertJsonPath('data.design.blocks.0.url', Storage::disk('public')->url($path))
            ->assertJsonPath('data.design.blocks.1.logo', null);

        $this->assertSame($path, $user->refresh()->vcard->design['blocks'][0]['url']);
    }

    public function test_refuses_files_that_are_not_pictures(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->post(route('api.v1.me.vcard-media.store'), ['image' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('image');
    }
}
