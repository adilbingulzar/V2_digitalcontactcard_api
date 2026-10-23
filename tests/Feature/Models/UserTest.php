<?php

namespace Tests\Feature\Models;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_role_and_status_cannot_be_mass_assigned(): void
    {
        $user = User::factory()->unverified()->create();

        $user->fill(['role' => UserRole::Admin, 'status' => UserStatus::Active])->save();

        $user->refresh();
        $this->assertSame(UserRole::User, $user->role);
        $this->assertSame(UserStatus::Pending, $user->status);
        $this->assertFalse($user->isAdmin());
    }

    public function test_serialized_user_does_not_expose_credentials_or_push_token(): void
    {
        $user = User::factory()->withLegacyPassword()->create(['push_token' => 'device-token']);

        $serialized = $user->toArray();

        $this->assertArrayNotHasKey('password', $serialized);
        $this->assertArrayNotHasKey('legacy_password_md5', $serialized);
        $this->assertArrayNotHasKey('remember_token', $serialized);
        $this->assertArrayNotHasKey('push_token', $serialized);
    }

    public function test_deleting_a_user_keeps_the_row_for_history(): void
    {
        $user = User::factory()->create();

        $user->delete();

        $this->assertSoftDeleted($user);
    }
}
