<?php

namespace Tests\Feature\Http\Controllers\Api\V1\Me;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TipPageBackgroundControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_uploads_and_removes_a_background_image(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->post(route('api.v1.me.tip-page.background.store'), ['image' => UploadedFile::fake()->image('bg.png', 2400, 1600)], ['Accept' => 'application/json']);

        $response->assertOk();
        $path = $user->refresh()->tipPageSetting->background_image;
        Storage::disk('public')->assertExists($path);
        $this->assertSame(Storage::disk('public')->url($path), $response->json('data.background_image'));

        $this->deleteJson(route('api.v1.me.tip-page.background.destroy'))->assertOk()->assertJsonPath('data.background_image', null);
        Storage::disk('public')->assertMissing($path);
    }
}
