<?php

namespace App\Console\Commands;

use App\Services\WebPush\WebPush;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Makes the key pair the site signs push messages with. Run once per
 * environment and put the two lines in .env; changing them later means
 * browsers have to allow notifications again.
 */
#[Signature('tiqs:webpush-keys')]
#[Description('Generate the VAPID key pair for browser push notifications')]
class GenerateWebPushKeys extends Command
{
    public function handle(): int
    {
        $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        openssl_pkey_export($key, $privatePem);
        $details = openssl_pkey_get_details($key);
        $public = "\x04".str_pad($details['ec']['x'], 32, "\0", STR_PAD_LEFT).str_pad($details['ec']['y'], 32, "\0", STR_PAD_LEFT);

        $this->line('Add these lines to .env (keep the private key secret):');
        $this->newLine();
        $this->line('WEBPUSH_PUBLIC_KEY='.WebPush::base64UrlEncode($public));
        $this->line('WEBPUSH_PRIVATE_KEY='.base64_encode($privatePem));

        return self::SUCCESS;
    }
}
