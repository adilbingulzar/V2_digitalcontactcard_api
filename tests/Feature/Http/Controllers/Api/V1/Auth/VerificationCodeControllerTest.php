<?php

namespace Tests\Feature\Http\Controllers\Api\V1\Auth;

use App\Mail\VerificationCodeMail;
use App\Models\User;
use App\Models\VerificationCode;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VerificationCodeControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_sends_a_new_code_and_the_old_code_stops_working(): void
    {
        $user = User::factory()->unverified()->create();
        $oldCode = VerificationCode::factory()->for($user)->create();
        Mail::fake();
        Sanctum::actingAs($user);

        $this->postJson(route('api.v1.auth.verification.send'))->assertNoContent();

        $this->assertModelMissing($oldCode);
        $this->assertSame(1, VerificationCode::whereBelongsTo($user)->count());
        Mail::assertQueued(VerificationCodeMail::class, fn (VerificationCodeMail $mail): bool => $mail->hasTo($user->email));
    }

    public function test_does_not_send_a_code_to_a_confirmed_account(): void
    {
        Mail::fake();
        Sanctum::actingAs(User::factory()->create());

        $this->postJson(route('api.v1.auth.verification.send'))->assertNoContent();

        Mail::assertNothingQueued();
    }

    public function test_returns_429_after_three_requests_in_five_minutes(): void
    {
        Mail::fake();
        Sanctum::actingAs(User::factory()->unverified()->create());

        foreach (range(1, 3) as $request) {
            $this->postJson(route('api.v1.auth.verification.send'))->assertNoContent();
        }

        $this->postJson(route('api.v1.auth.verification.send'))->assertTooManyRequests();
    }
}
