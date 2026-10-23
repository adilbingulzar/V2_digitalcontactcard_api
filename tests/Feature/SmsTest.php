<?php

namespace Tests\Feature;

use App\Actions\Auth\SendVerificationCode;
use App\Jobs\SendSms;
use App\Models\SmsMessage;
use App\Models\User;
use App\Services\Sms\SmsGateway;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SmsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_verification_codes_are_also_texted_when_the_user_has_a_phone(): void
    {
        Mail::fake();
        Queue::fake();
        $withPhone = User::factory()->unverified()->create(['country_code' => '31', 'phone' => '612345678']);
        $withoutPhone = User::factory()->unverified()->create(['phone' => null]);

        app(SendVerificationCode::class)->handle($withPhone);
        app(SendVerificationCode::class)->handle($withoutPhone);

        Queue::assertPushed(SendSms::class, 1);
        $sms = SmsMessage::sole();
        $this->assertSame(['612345678', 'verification', 'queued', $withPhone->id], [$sms->phone, $sms->purpose, $sms->status, $sms->user_id]);
        $this->assertStringContainsString('verification code', $sms->message);
    }

    public function test_numbers_go_to_the_right_gateway(): void
    {
        config([
            'services.sms.tiqs' => ['url' => 'https://sms.tiqs.test/send', 'api_key' => 'tiqs-key'],
            'services.sms.smscountry' => ['url' => 'https://smscountry.test/api', 'auth_token' => 'uae-token', 'sender_id' => 'TIQS'],
        ]);
        Http::fake();

        $this->assertTrue(app(SmsGateway::class)->send('31', '06 1234 5678', 'Hello'));
        $this->assertTrue(app(SmsGateway::class)->send('+971', '501234567', 'Marhaba'));

        Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://sms.tiqs.test/send?')
            && $request['mobile'] === '0612345678' && $request->hasHeader('X-API-KEY', 'tiqs-key'));
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://smscountry.test/api'
            && $request['Number'] === '971501234567' && $request->hasHeader('Authorization', 'uae-token'));
    }

    public function test_without_a_gateway_nothing_is_sent(): void
    {
        config(['services.sms.tiqs.api_key' => null, 'services.sms.smscountry.auth_token' => null]);
        Http::fake();

        $this->assertFalse(app(SmsGateway::class)->send('31', '612345678', 'Hello'));

        Http::assertNothingSent();
    }

    public function test_the_job_records_the_outcome(): void
    {
        config(['services.sms.tiqs' => ['url' => 'https://sms.tiqs.test/send', 'api_key' => 'tiqs-key']]);
        Http::fake(['sms.tiqs.test/*' => Http::response('', 500)]);
        $sms = SmsMessage::factory()->create(['status' => 'queued', 'sent_at' => null]);
        $job = new SendSms($sms->id);

        try {
            $job->handle(app(SmsGateway::class));
            $this->fail('A refused message must throw, so the queue retries it.');
        } catch (\RuntimeException) {
            $job->failed(new \RuntimeException('The SMS gateway answered with HTTP 500.'));
        }

        $this->assertSame('failed', $sms->fresh()->status);
        $this->assertSame('The SMS gateway answered with HTTP 500.', $sms->fresh()->error);
    }
}
