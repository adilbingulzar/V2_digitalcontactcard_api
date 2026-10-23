<?php

namespace Tests\Feature\Http\Controllers\Api\V1\Me;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AvatarControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_stores_the_photo_re_encoded_as_webp_and_removes_the_previous_one(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('avatars/old.webp', 'old');
        $user = User::factory()->create(['avatar' => 'avatars/old.webp']);
        Sanctum::actingAs($user);

        $this->post(route('api.v1.me.avatar.store'), ['image' => UploadedFile::fake()->image('me.jpg', 1200, 900)], ['Accept' => 'application/json'])
            ->assertOk();

        $path = $user->refresh()->avatar;
        $this->assertStringStartsWith('avatars/', $path);
        $this->assertStringEndsWith('.webp', $path);
        $this->assertSame('image/webp', getimagesizefromstring(Storage::disk('public')->get($path))['mime']);
        $this->assertLessThanOrEqual(600, getimagesizefromstring(Storage::disk('public')->get($path))[0]);
        Storage::disk('public')->assertMissing('avatars/old.webp');
    }

    public function test_rejects_a_file_that_is_not_an_image(): void
    {
        Storage::fake('public');
        Sanctum::actingAs(User::factory()->create());

        $this->post(route('api.v1.me.avatar.store'), ['image' => UploadedFile::fake()->createWithContent('me.jpg', '<?php echo "hi";')], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['image']);
    }

    public function test_keeps_a_photo_that_lives_on_the_old_app_when_replacing_it(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['avatar' => 'legacy-photo.jpg']);
        Sanctum::actingAs($user);

        $this->post(route('api.v1.me.avatar.store'), ['image' => UploadedFile::fake()->image('me.png')], ['Accept' => 'application/json'])->assertOk();

        $this->assertStringStartsWith('avatars/', $user->refresh()->avatar);
    }

    public function test_removes_the_photo(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('avatars/me.webp', 'x');
        $user = User::factory()->create(['avatar' => 'avatars/me.webp']);
        Sanctum::actingAs($user);

        $this->deleteJson(route('api.v1.me.avatar.destroy'))->assertOk()->assertJsonPath('data.avatar', null);

        Storage::disk('public')->assertMissing('avatars/me.webp');
    }
}
