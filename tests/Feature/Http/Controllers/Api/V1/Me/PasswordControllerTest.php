<?php

namespace Tests\Feature\Http\Controllers\Api\V1\Me;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_changes_the_password_and_signs_out_other_devices(): void
    {
        $user = User::factory()->create();
        $thisDevice = $user->createToken('this');
        $otherDevice = $user->createToken('other');

        $this->withToken($thisDevice->plainTextToken)->putJson(route('api.v1.me.password.update'), [
            'current_password' => 'password',
            'password' => 'NewSecret123',
            'password_confirmation' => 'NewSecret123',
        ])->assertNoContent();

        $this->assertTrue(Hash::check('NewSecret123', $user->fresh()->password));
        $this->assertModelExists($thisDevice->accessToken);
        $this->assertModelMissing($otherDevice->accessToken);
    }

    public function test_accepts_a_current_password_still_stored_by_the_old_app(): void
    {
        $user = User::factory()->withLegacyPassword('oldsecret')->create();

        $this->withToken($user->createToken('this')->plainTextToken)->putJson(route('api.v1.me.password.update'), [
            'current_password' => 'oldsecret',
            'password' => 'NewSecret123',
            'password_confirmation' => 'NewSecret123',
        ])->assertNoContent();

        $this->assertNull($user->fresh()->legacy_password_md5);
    }

    public function test_requires_the_current_password(): void
    {
        $user = User::factory()->create();

        $this->withToken($user->createToken('this')->plainTextToken)->putJson(route('api.v1.me.password.update'), [
            'current_password' => 'wrong',
            'password' => 'NewSecret123',
            'password_confirmation' => 'NewSecret123',
        ])->assertUnprocessable()->assertJsonValidationErrors(['current_password' => 'The password is incorrect.']);

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }
}
