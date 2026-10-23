<?php

namespace Tests\Feature;

use App\Models\TipPageSetting;
use App\Models\User;
use App\Models\Vcard;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportLegacyImagesTest extends TestCase
{
    use LazilyRefreshDatabase;

    private string $legacy;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->legacy = storage_path('framework/testing/legacy-'.bin2hex(random_bytes(4)));
        foreach (['users', 'business', 'vcard', 'pattern'] as $folder) {
            File::ensureDirectoryExists($this->legacy.'/assets/images/'.$folder);
        }
        File::put($this->legacy.'/secret.txt', 'not an image');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->legacy);
        parent::tearDown();
    }

    private function png(string $path): void
    {
        $image = imagecreatetruecolor(20, 20);
        imagepng($image, $this->legacy.'/assets/images/'.$path);
    }

    public function test_copies_old_images_and_points_records_at_them(): void
    {
        $this->png('users/user-7.png');
        $this->png('business/logo-7.png');
        $this->png('pattern/pattern2.png');
        $user = User::factory()->create(['avatar' => 'user-7.png']);
        $placeholder = User::factory()->create(['avatar' => 'user-image.jpg']);
        $missing = User::factory()->create(['avatar' => 'gone.jpg']);
        $sneaky = User::factory()->create(['avatar' => '../../secret.txt']);
        $card = Vcard::factory()->for($user)->create(['logo' => 'logo-7.png', 'banner' => 'default.jpg']);
        $page = TipPageSetting::factory()->for($user)->create(['background_image' => 'pattern2.png']);

        $this->artisan('tiqs:import-images', ['--legacy-path' => $this->legacy])->assertSuccessful();

        $this->assertStringStartsWith('avatars/', $user->fresh()->avatar);
        Storage::disk('public')->assertExists($user->fresh()->avatar);
        $this->assertStringStartsWith('vcard-logos/', $card->fresh()->logo);
        $this->assertSame('default.jpg', $card->fresh()->banner);
        $this->assertStringStartsWith('tip-backgrounds/', $page->fresh()->background_image);
        $this->assertSame('user-image.jpg', $placeholder->fresh()->avatar);
        $this->assertSame('gone.jpg', $missing->fresh()->avatar);
        $this->assertSame('../../secret.txt', $sneaky->fresh()->avatar);

        // A second run changes nothing.
        $copied = $user->fresh()->avatar;
        $this->artisan('tiqs:import-images', ['--legacy-path' => $this->legacy])->assertSuccessful();
        $this->assertSame($copied, $user->fresh()->avatar);
    }

    public function test_dry_run_changes_nothing(): void
    {
        $this->png('users/user-7.png');
        $user = User::factory()->create(['avatar' => 'user-7.png']);

        $this->artisan('tiqs:import-images', ['--legacy-path' => $this->legacy, '--dry-run' => true])->assertSuccessful();

        $this->assertSame('user-7.png', $user->fresh()->avatar);
    }
}
