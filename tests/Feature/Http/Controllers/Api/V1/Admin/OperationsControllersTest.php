<?php

namespace Tests\Feature\Http\Controllers\Api\V1\Admin;

use App\Enums\EmployeeStatus;
use App\Enums\PaymentStatus;
use App\Jobs\SendSms;
use App\Mail\AccountInviteMail;
use App\Models\Business;
use App\Models\FeeCollection;
use App\Models\SmsMessage;
use App\Models\Tip;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Fees, SMS and bulk sign-up.
 */
class OperationsControllersTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create(['phone' => null]);
        Sanctum::actingAs($this->admin);
    }

    public function test_fee_report_counts_only_paid_fees(): void
    {
        FeeCollection::factory()->count(2)->create(['fee_amount' => 0.25, 'payment_status' => PaymentStatus::Paid]);
        FeeCollection::factory()->create(['fee_amount' => 5, 'payment_status' => PaymentStatus::Cancelled]);

        $this->getJson(route('api.v1.admin.fees.index'))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.totals.fees', '0.50');
    }

    public function test_sends_sms_to_several_numbers_and_can_drop_unicode(): void
    {
        Queue::fake();

        $this->postJson(route('api.v1.admin.sms.store'), [
            'audience' => 'numbers', 'country_code' => '31', 'phones' => '612345678, 0687654321,612345678', 'message' => 'Café €5 offer', 'unicode' => false,
        ])->assertAccepted()->assertJsonPath('data.queued', 2);

        $this->assertSame(['612345678', '0687654321'], SmsMessage::orderBy('id')->pluck('phone')->all());
        $this->assertSame('Cafe EUR5 offer', SmsMessage::first()->message);

        $this->postJson(route('api.v1.admin.sms.store'), ['audience' => 'numbers', 'country_code' => '31', 'phones' => '12, abc', 'message' => 'Hi'])
            ->assertJsonValidationErrors('phones');
    }

    public function test_sends_sms_to_a_number_or_to_tipped_users(): void
    {
        Queue::fake();
        $tipped = User::factory()->create(['country_code' => '31', 'phone' => '611111111']);
        Tip::factory()->for($tipped, 'recipient')->create(['payment_status' => PaymentStatus::Paid]);
        User::factory()->create(['country_code' => '31', 'phone' => '622222222']);

        $this->postJson(route('api.v1.admin.sms.store'), ['audience' => 'number', 'country_code' => '+971', 'phone' => '501234567', 'message' => 'Hi'])
            ->assertAccepted()->assertJsonPath('data.queued', 1);
        $this->postJson(route('api.v1.admin.sms.store'), ['audience' => 'tipped_users', 'message' => 'Thanks for using us'])
            ->assertAccepted()->assertJsonPath('data.queued', 1);

        Queue::assertPushed(SendSms::class, 2);
        $this->assertSame(['971', '31'], SmsMessage::orderBy('id')->pluck('country_code')->all());
        $this->assertSame([$this->admin->id], SmsMessage::distinct()->pluck('sent_by')->all());
        $this->getJson(route('api.v1.admin.sms.index'))->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.to', '+31 611111111');
    }

    public function test_imports_accounts_from_csv(): void
    {
        Mail::fake();
        $business = Business::factory()->create();
        User::factory()->create(['email' => 'taken@example.com']);
        $csv = "first_name,last_name,title,email,phone,social_1,social_2,social_3,social_4,appointment_url,ticket_url,address,latitude,longitude\n"
            ."Anna,de Vries,Waiter,Anna@Example.com,06 1234 5678,https://instagram.com/anna,javascript:alert(1),,,https://cal.example.com/anna,,Dam 1 Amsterdam,52.37,4.89\n"
            ."Bob,,,taken@example.com,,,,,,,,,,\n"
            ."Carl,,,not-an-email,,,,,,,,,,\n";

        $response = $this->post(route('api.v1.admin.account-imports.store'), [
            'file' => UploadedFile::fake()->createWithContent('people.csv', $csv),
            'business_id' => $business->id,
            'country_code' => '+31',
            'country' => 'nl',
            'theme' => 1,
            'account_type' => 'business',
            'message' => 'Welcome to the team!',
        ], ['Accept' => 'application/json'])->assertOk();

        $response->assertJsonPath('data.created', ['anna@example.com']);
        $this->assertCount(2, $response->json('data.skipped'));
        $anna = User::where('email', 'anna@example.com')->sole();
        $this->assertSame('anna'.$anna->id, $anna->slug);
        $this->assertTrue($anna->hasVerifiedEmail());
        $this->assertSame('0612345678', $anna->phone);
        $this->assertSame(['https://instagram.com/anna'], $anna->vcard->social_links);
        $this->assertSame('Waiter', $anna->vcard->business_title);
        $this->assertSame(EmployeeStatus::Accepted, $anna->employments()->sole()->status);
        Mail::assertQueued(AccountInviteMail::class, fn (AccountInviteMail $mail): bool => $mail->hasTo('anna@example.com') && str_contains($mail->link, '/reset-password?token=') && $mail->note === 'Welcome to the team!');
    }

    public function test_a_business_import_needs_a_business(): void
    {
        $this->post(route('api.v1.admin.account-imports.store'), [
            'file' => UploadedFile::fake()->createWithContent('people.csv', "first_name,email\nAnna,anna@example.com\n"),
            'country_code' => '+31',
            'country' => 'nl',
            'theme' => 1,
            'account_type' => 'business',
        ], ['Accept' => 'application/json'])->assertJsonValidationErrors('business_id');
    }
}
