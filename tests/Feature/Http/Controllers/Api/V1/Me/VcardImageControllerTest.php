<?php

namespace Tests\Feature\Http\Controllers\Api\V1\Me;

use App\Models\User;
use App\Models\Vcard;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VcardImageControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_uploads_and_replaces_the_banner(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->post(route('api.v1.me.vcard.image.store', 'banner'), ['image' => UploadedFile::fake()->image('a.jpg', 800, 400)], ['Accept' => 'application/json'])->assertOk();
        $first = $user->vcard()->value('banner');
        $this->post(route('api.v1.me.vcard.image.store', 'banner'), ['image' => UploadedFile::fake()->image('b.jpg', 800, 400)], ['Accept' => 'application/json'])->assertOk();

        $second = $user->vcard()->value('banner');
        $this->assertStringStartsWith('vcard-banners/', $second);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);
    }

    public function test_the_logo_cannot_be_removed_while_it_replaces_the_name(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        Vcard::factory()->for($user)->create(['show_business_name' => false, 'logo' => 'vcard-logos/logo.webp']);
        Sanctum::actingAs($user);

        $this->deleteJson(route('api.v1.me.vcard.image.destroy', 'logo'))->assertUnprocessable();
        $this->deleteJson(route('api.v1.me.vcard.image.destroy', 'avatar'))->assertNotFound();
    }
}
