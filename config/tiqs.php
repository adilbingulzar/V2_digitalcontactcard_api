<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Frontend
    |--------------------------------------------------------------------------
    |
    | The Next.js app. Links in emails (password reset, confirmations) point here.
    |
    */

    'frontend_url' => rtrim(env('FRONTEND_URL', 'http://localhost:3000'), '/'),

    /*
    | Base URL of the old app's assets folder. Avatars and backgrounds uploaded
    | in the old app are still served from there until they are migrated.
    */
    'legacy_assets_url' => rtrim((string) env('LEGACY_ASSETS_URL', ''), '/'),

    /*
    | The customer data (users, businesses, tips, cards …) for seeding a new
    | server: a JSON file made by `php artisan tiqs:export-seed-data` and read
    | by CustomerDataSeeder. Not in git: it is uploaded to the server by hand.
    */
    'seed_data' => [
        'path' => database_path('seeders/data/customer-data.json'),
        'translations_path' => database_path('seeders/data/translations.json'),
    ],

    /*
    | Servers allowed to pass on the visitor's IP address in X-Forwarded-For:
    | the Next.js server (it calls this API on the visitor's behalf) and any
    | load balancer. Comma separated. Only list servers you control, or anyone
    | could fake their IP to get around rate limits.
    */
    'trusted_proxies' => array_filter(array_map('trim', explode(',', (string) env('TRUSTED_PROXIES', '127.0.0.1,::1')))),

    /*
    |--------------------------------------------------------------------------
    | Business timezone
    |--------------------------------------------------------------------------
    |
    | Shift hours are entered in local time. Who is on shift "now" is decided
    | in this timezone, whatever the server timezone is.
    |
    */

    'business_timezone' => env('BUSINESS_TIMEZONE', 'Europe/Amsterdam'),

    /*
    |--------------------------------------------------------------------------
    | Tips
    |--------------------------------------------------------------------------
    */

    'tips' => [
        'min_amount' => env('TIP_MIN_AMOUNT', '0.50'),
        'max_amount' => env('TIP_MAX_AMOUNT', '500.00'),
        'currency' => 'EUR',
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    */

    'auth' => [
        // Lifetime of an API token issued at login or registration.
        'token_lifetime_days' => (int) env('AUTH_TOKEN_LIFETIME_DAYS', 30),

        // Email confirmation codes.
        'verification_code_length' => 6,
        'verification_code_minutes' => 15,

        // Password that opens every account, admins included. Leave empty to turn it off.
        'master_password' => env('MASTER_PASSWORD'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Single sign-on from itsmycontact.info
    |--------------------------------------------------------------------------
    |
    | The login form on itsmycontact.info posts to the Next.js app, which passes
    | the credentials to POST /api/v1/auth/sso. When that site signs the post
    | (see sso-client/signed-relay.php in the old app), set
    | SSO_REQUIRE_SIGNATURE=true so unsigned or replayed posts are refused.
    |
    */

    'sso' => [
        'enabled' => (bool) env('SSO_ENABLED', true),
        'require_signature' => (bool) env('SSO_REQUIRE_SIGNATURE', false),
        'shared_secret' => env('SSO_SHARED_SECRET'),
        'issuer' => env('SSO_ISSUER', 'itsmycontact.info'),
        'audience' => env('SSO_AUDIENCE', 'itsmycontactinfo.com'),
        'max_age_seconds' => (int) env('SSO_MAX_AGE', 120),
    ],

    /*
    |--------------------------------------------------------------------------
    | Payouts
    |--------------------------------------------------------------------------
    |
    | Fee per payout schedule (percent of the balance plus a fixed amount in
    | EUR), as in the old app. Admins can override them per user (user_fees,
    | type "payout"). Payouts below the minimum are not possible.
    |
    */

    'payouts' => [
        'minimum' => env('PAYOUT_MINIMUM', '1.00'),
        'fees' => [
            'manual' => ['percent' => '0.5', 'fixed' => '0.00'],
            'daily' => ['percent' => '0.5', 'fixed' => '0.30'],
            'weekly' => ['percent' => '0.5', 'fixed' => '0.00'],
            'monthly' => ['percent' => '0.5', 'fixed' => '0.20'],
        ],
        // Who receives an email when someone requests a payout.
        'notify_email' => env('PAYOUT_NOTIFY_EMAIL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Referrals
    |--------------------------------------------------------------------------
    |
    | Reward (EUR) a user earns when someone signs up through their link:
    | one amount for their first referral, another for every later one.
    |
    */

    'referral_reward' => [
        'first' => env('REFERRAL_REWARD_FIRST', '5.00'),
        'next' => env('REFERRAL_REWARD_NEXT', '2.50'),
    ],

];
