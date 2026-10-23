<?php

namespace Tests\Feature\Http\Controllers\Api\V1\Auth;

use App\Enums\AccountType;
use App\Enums\EmployeeStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Mail\VerificationCodeMail;
use App\Models\Business;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RegisteredUserControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_valid_registration_creates_pending_account_returns_token_and_emails_code(): void
    {
        Mail::fake();

        $response = $this->postJson(route('api.v1.auth.register'), $this->payload());

        $response->assertCreated()
            ->assertJsonStructure(['token', 'user' => ['id', 'email', 'slug']])
            ->assertJsonPath('user.email_verified', false);

        $user = User::where('email', 'anna@example.com')->sole();
        $this->assertSame('Anna de Vries', $user->name);
        $this->assertSame(UserStatus::Pending, $user->status);
        $this->assertSame('anna'.$user->id, $user->slug);
        $this->assertSame('31', $user->country_code);
        $this->assertTrue(password_verify('secret123', $user->password));
        Mail::assertQueued(VerificationCodeMail::class, fn (VerificationCodeMail $mail): bool => $mail->hasTo('anna@example.com'));
    }

    public function test_business_registration_creates_the_business(): void
    {
        Mail::fake();

        $this->postJson(route('api.v1.auth.register'), $this->payload([
            'account_type' => AccountType::Business->value,
            'business_name' => 'Cafe Anna',
        ]))->assertCreated()->assertJsonPath('user.business.name', 'Cafe Anna');

        $business = Business::sole();
        $this->assertSame('anna@example.com', $business->owner->email);
        $this->assertMatchesRegularExpression('/^BIZ\d{8}$/', $business->code);
    }

    public function test_business_registration_requires_a_business_name(): void
    {
        $this->postJson(route('api.v1.auth.register'), $this->payload(['account_type' => AccountType::Business->value]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['business_name' => 'The business name field is required.']);
    }

    public function test_signing_up_through_a_business_referral_creates_reward_and_join_request(): void
    {
        Mail::fake();
        $business = Business::factory()->create();

        $this->postJson(route('api.v1.auth.register'), $this->payload(['referral' => $business->owner->slug]))->assertCreated();

        $user = User::where('email', 'anna@example.com')->sole();
        $referral = Referral::sole();
        $this->assertTrue($referral->referrer->is($business->owner));
        $this->assertSame('5.00', $referral->amount);
        $this->assertFalse($referral->is_confirmed);
        $employee = $user->employments()->sole();
        $this->assertTrue($employee->business->is($business));
        $this->assertSame(EmployeeStatus::Pending, $employee->status);
    }

    public function test_cannot_register_as_admin_or_active_through_the_payload(): void
    {
        Mail::fake();

        $this->postJson(route('api.v1.auth.register'), $this->payload(['role' => 'admin', 'status' => 'active']))->assertCreated();

        $user = User::where('email', 'anna@example.com')->sole();
        $this->assertSame(UserRole::User, $user->role);
        $this->assertSame(UserStatus::Pending, $user->status);
    }

    public function test_rejects_an_email_that_is_already_registered_in_any_letter_case(): void
    {
        User::factory()->create(['email' => 'anna@example.com']);

        $this->postJson(route('api.v1.auth.register'), $this->payload(['email' => 'Anna@Example.com']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => 'The email has already been taken.']);
    }

    public function test_empty_payload_reports_every_required_field(): void
    {
        $this->postJson(route('api.v1.auth.register'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['first_name', 'last_name', 'email', 'password', 'account_type', 'phone', 'country_code', 'country', 'accept_terms']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Anna',
            'last_name' => 'de Vries',
            'email' => 'anna@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'account_type' => AccountType::Private->value,
            'phone' => '612345678',
            'country_code' => '+31',
            'country' => 'NL',
            'accept_terms' => true,
        ], $overrides);
    }
}
