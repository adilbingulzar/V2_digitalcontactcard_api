<?php

/*
|--------------------------------------------------------------------------
| Payment methods
|--------------------------------------------------------------------------
|
| Payment methods a customer can choose on a tip page, keyed by Pay.nl
| payment method id. The fee (a percentage plus a fixed amount in EUR) is
| added to what the customer pays, as in the old app. Methods with
| "public" set to false are not offered to customers.
|
| Users can limit which of these they offer (payment_methods table).
|
*/

return [
    10 => ['name' => 'iDEAL', 'icon' => 'ideal', 'fee_percent' => '0', 'fee_fixed' => '0.25', 'public' => true],
    706 => ['name' => 'Credit card', 'icon' => 'credit-card', 'fee_percent' => '2', 'fee_fixed' => '0.35', 'public' => true],
    436 => ['name' => 'Bancontact', 'icon' => 'bancontact', 'fee_percent' => '0', 'fee_fixed' => '0.25', 'public' => true],
    694 => ['name' => 'Giropay', 'icon' => 'giropay', 'fee_percent' => '0', 'fee_fixed' => '0.25', 'public' => true],
    2379 => ['name' => 'Payconiq', 'icon' => 'payconiq', 'fee_percent' => '0', 'fee_fixed' => '0.25', 'public' => true],
    559 => ['name' => 'My Bank', 'icon' => 'mybank', 'fee_percent' => '1', 'fee_fixed' => '0.25', 'public' => true],
    1705 => ['name' => 'American Express', 'icon' => 'amex', 'fee_percent' => '4', 'fee_fixed' => '0.30', 'public' => true],
    710 => ['name' => 'Carte Bleue', 'icon' => 'carte-bleue', 'fee_percent' => '3', 'fee_fixed' => '0.30', 'public' => true],
    2558 => ['name' => 'Google Pay', 'icon' => 'google-pay', 'fee_percent' => '4', 'fee_fixed' => '0.30', 'public' => true],
    2277 => ['name' => 'Apple Pay', 'icon' => 'apple-pay', 'fee_percent' => '4', 'fee_fixed' => '0.30', 'public' => true],
    138 => ['name' => 'PayPal', 'icon' => 'paypal', 'fee_percent' => '3.5', 'fee_fixed' => '0.30', 'public' => true],
];
