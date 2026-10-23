<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Sends text messages through the same two gateways as the old app: SMSCountry
 * for numbers in the United Arab Emirates (+971), the TIQS gateway for all
 * others. Without credentials (as in development) messages are only logged.
 */
class SmsGateway
{
    /**
     * @param  string  $countryCode  Calling code without "+", e.g. "31"
     * @return bool false when no gateway is configured and the message was only logged
     *
     * @throws RuntimeException when the gateway refuses the message
     */
    public function send(string $countryCode, string $phone, string $message): bool
    {
        $countryCode = ltrim(trim($countryCode), '+0');
        $phone = preg_replace('/\D/', '', $phone) ?? '';

        if ($countryCode === '' || $phone === '') {
            throw new RuntimeException('The phone number is incomplete.');
        }

        if ($countryCode === '971' && filled(config('services.sms.smscountry.auth_token'))) {
            $response = Http::timeout(15)
                ->withHeaders(['Authorization' => (string) config('services.sms.smscountry.auth_token')])
                ->post((string) config('services.sms.smscountry.url'), [
                    'Text' => $message,
                    'Number' => $countryCode.$phone,
                    'SenderId' => config('services.sms.smscountry.sender_id'),
                    'Tool' => 'API',
                ]);
        } elseif (filled(config('services.sms.tiqs.api_key'))) {
            $response = Http::timeout(15)
                ->withHeaders(['X-API-KEY' => (string) config('services.sms.tiqs.api_key')])
                ->get((string) config('services.sms.tiqs.url'), ['country_code' => $countryCode, 'mobile' => $phone, 'message' => $message]);
        } else {
            Log::info('SMS not sent: no SMS gateway is configured.', ['to' => '+'.$countryCode.substr($phone, 0, 2).'…']);

            return false;
        }

        if ($response->failed()) {
            throw new RuntimeException('The SMS gateway answered with HTTP '.$response->status().'.');
        }

        return true;
    }
}
