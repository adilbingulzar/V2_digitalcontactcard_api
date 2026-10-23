<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    | Pay.nl Order API (https://developer.pay.nl/reference/api_create_order-1).
    | Basic auth with the AT-code and API token; the SL-code identifies the
    | sales location (service) the payments belong to.
    */
    'paynl' => [
        'base_url' => env('PAYNL_BASE_URL', 'https://connect.pay.nl'),
        'username' => env('PAYNL_USERNAME'),
        'password' => env('PAYNL_PASSWORD'),
        'service_id' => env('PAYNL_SERVICE_ID'),
        'timeout' => (int) env('PAYNL_TIMEOUT', 10),
    ],

    /*
     * Text messages (verification codes, password reset links). Numbers in the
     * UAE go through SMSCountry, all others through the TIQS gateway. Leave
     * the keys empty to only log messages.
     */
    'sms' => [
        'tiqs' => [
            'url' => env('SMS_TIQS_URL'),
            'api_key' => env('SMS_TIQS_API_KEY'),
        ],
        'smscountry' => [
            'url' => env('SMS_SMSCOUNTRY_URL', 'https://restapi.smscountry.com'),
            'auth_token' => env('SMS_SMSCOUNTRY_AUTH_TOKEN'),
            'sender_id' => env('SMS_SMSCOUNTRY_SENDER_ID'),
        ],
    ],

    /*
     * Fills in missing translations automatically (as the old app did), when
     * a Google Cloud Translation API key is set. Admins can correct them.
     */
    'google_translate' => [
        'key' => env('GOOGLE_TRANSLATE_KEY'),
    ],

    /*
     * Identity verification (KYC) with Sumsub: app token and secret from the
     * Sumsub dashboard, the verification level, and the webhook secret.
     */
    'sumsub' => [
        'base_url' => env('SUMSUB_BASE_URL', 'https://api.sumsub.com'),
        'app_token' => env('SUMSUB_APP_TOKEN'),
        'secret_key' => env('SUMSUB_SECRET_KEY'),
        'level_name' => env('SUMSUB_LEVEL_NAME', 'basic-kyc-level'),
        'webhook_secret' => env('SUMSUB_WEBHOOK_SECRET'),
    ],

    /*
     * Browser push notifications (Web Push with VAPID). Make the keys with
     * `php artisan tiqs:webpush-keys`; leave empty to switch push off.
     */
    'webpush' => [
        'public_key' => env('WEBPUSH_PUBLIC_KEY'),
        'private_key' => env('WEBPUSH_PRIVATE_KEY'),
        'subject' => env('WEBPUSH_SUBJECT', 'mailto:info@tiqs.com'),
    ],

    /*
     * "Add to Google Wallet" for business cards: the issuer id from the Google
     * Pay & Wallet console and the path of a service account JSON key file
     * (kept outside the repository).
     */
    'google_wallet' => [
        'issuer_id' => env('GOOGLE_WALLET_ISSUER_ID'),
        'credentials' => env('GOOGLE_WALLET_CREDENTIALS'),
    ],

    /*
     * Apple Wallet passes for business cards: a Pass Type ID certificate (.p12)
     * with its password, and Apple's WWDR intermediate certificate (.pem).
     */
    'apple_wallet' => [
        'pass_type_id' => env('APPLE_WALLET_PASS_TYPE_ID'),
        'team_id' => env('APPLE_WALLET_TEAM_ID'),
        'certificate' => env('APPLE_WALLET_CERTIFICATE'),
        'certificate_password' => env('APPLE_WALLET_CERTIFICATE_PASSWORD'),
        'wwdr_certificate' => env('APPLE_WALLET_WWDR_CERTIFICATE'),
    ],

];
