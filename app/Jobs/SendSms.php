<?php

namespace App\Jobs;

use App\Models\SmsMessage;
use App\Services\Sms\SmsGateway;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Throwable;

/**
 * Sends one text message in the background, so a slow gateway never slows
 * down a page. Each message is recorded for the admin SMS report.
 */
class SendSms implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(public int $smsMessageId) {}

    /**
     * Records the message and queues it.
     */
    public static function queueMessage(string $countryCode, string $phone, string $message, string $purpose, ?int $userId = null, ?int $sentBy = null): SmsMessage
    {
        $sms = SmsMessage::create([
            'user_id' => $userId,
            'sent_by' => $sentBy,
            'purpose' => $purpose,
            'country_code' => ltrim(trim($countryCode), '+'),
            'phone' => trim($phone),
            'message' => $message,
            'status' => 'queued',
        ]);

        self::dispatch($sms->id);

        return $sms;
    }

    public function handle(SmsGateway $gateway): void
    {
        $sms = SmsMessage::find($this->smsMessageId);
        if ($sms === null || $sms->status === 'sent') {
            return;
        }

        $sent = $gateway->send($sms->country_code, $sms->phone, $sms->message);
        $sms->forceFill(['status' => $sent ? 'sent' : 'logged', 'sent_at' => now(), 'error' => null])->save();
    }

    public function failed(?Throwable $exception): void
    {
        SmsMessage::whereKey($this->smsMessageId)->update([
            'status' => 'failed',
            'error' => Str::limit((string) $exception?->getMessage(), 250),
        ]);
    }
}
