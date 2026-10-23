<?php

namespace Tests\Feature\Http\Controllers\Api\V1\Me;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_updates_own_details_and_public_url_name(): void
    {
        $user = User::factory()->create(['slug' => 'old']);
        Sanctum::actingAs($user);

        $this->patchJson(route('api.v1.me.profile.update'), $this->payload(['slug' => 'Anna42', 'country_code' => '+31']))
            ->assertOk()
            ->assertJsonPath('data.slug', 'Anna42')
            ->assertJsonPath('data.bio', 'Happy to help');

        $user->refresh();
        $this->assertSame('31', $user->country_code);
        $this->assertTrue($user->accepts_individual_tips);
    }

    public function test_cannot_change_email_or_role_through_the_profile(): void
    {
        $user = User::factory()->create(['email' => 'me@example.com']);
        Sanctum::actingAs($user);

        $this->patchJson(route('api.v1.me.profile.update'), $this->payload(['email' => 'new@example.com', 'role' => 'admin']))->assertOk();

        $user->refresh();
        $this->assertSame('me@example.com', $user->email);
        $this->assertSame(UserRole::User, $user->role);
    }

    public function test_rejects_a_public_url_name_that_is_taken_or_reserved(): void
    {
        User::factory()->create(['slug' => 'taken']);
        Sanctum::actingAs(User::factory()->create());

        $this->patchJson(route('api.v1.me.profile.update'), $this->payload(['slug' => 'TAKEN']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['slug' => 'This name is already taken.']);

        $this->patchJson(route('api.v1.me.profile.update'), $this->payload(['slug' => 'spot']))
            ->assertJsonValidationErrors(['slug' => 'This name is reserved. Choose another one.']);

        $this->patchJson(route('api.v1.me.profile.update'), $this->payload(['slug' => 'anna-42']))
            ->assertJsonValidationErrors(['slug' => 'Use letters and numbers only.']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Anna de Vries',
            'slug' => 'anna',
            'phone' => '612345678',
            'country_code' => '31',
            'country' => 'NL',
            'birth_date' => '1990-06-15',
            'address' => 'Damrak 1, Amsterdam',
            'bio' => 'Happy to help',
            'accepts_individual_tips' => true,
        ], $overrides);
    }
}
