<?php

namespace Tests\Feature\Http\Controllers\Api\V1\Me;

use App\Mail\VerificationCodeMail;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmailControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_changing_the_email_requires_confirming_the_new_address(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->putJson(route('api.v1.auth.email.update'), ['email' => ' New@Example.com ', 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('data.email', 'new@example.com')
            ->assertJsonPath('data.email_verified', false);

        Mail::assertQueued(VerificationCodeMail::class, fn (VerificationCodeMail $mail): bool => $mail->hasTo('new@example.com'));
    }

    public function test_cannot_take_an_address_in_use_or_change_without_the_password(): void
    {
        Mail::fake();
        User::factory()->create(['email' => 'taken@example.com']);
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->putJson(route('api.v1.auth.email.update'), ['email' => 'taken@example.com', 'password' => 'wrong'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);

        Mail::assertNothingSent();
    }
}
