<?php

namespace Tests\Feature\Http\Controllers\Api\V1\Auth;

use App\Mail\PasswordResetMail;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetLinkControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_sends_a_reset_link_that_opens_the_frontend(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->postJson(route('api.v1.auth.password.email'), ['email' => $user->email])->assertOk();

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user): bool {
            $mail = $notification->toMail($user);

            return $mail instanceof PasswordResetMail
                && str_starts_with($mail->link, config('tiqs.frontend_url').'/reset-password?token=')
                && $mail->hasTo($user->email);
        });
    }

    public function test_shared_email_sends_the_link_for_the_oldest_account(): void
    {
        Notification::fake();
        $oldest = User::factory()->create(['email' => 'lia@example.com']);
        $newer = User::factory()->create(['email' => 'lia@example.com']);

        $this->postJson(route('api.v1.auth.password.email'), ['email' => 'lia@example.com'])->assertOk();

        Notification::assertSentTo($oldest, ResetPassword::class);
        Notification::assertNotSentTo($newer, ResetPassword::class);
    }

    public function test_unknown_email_gets_the_same_response_as_a_registered_one(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $known = $this->postJson(route('api.v1.auth.password.email'), ['email' => $user->email]);
        $unknown = $this->postJson(route('api.v1.auth.password.email'), ['email' => 'nobody@example.com']);

        $unknown->assertOk();
        $this->assertSame($known->json(), $unknown->json());
    }
}
