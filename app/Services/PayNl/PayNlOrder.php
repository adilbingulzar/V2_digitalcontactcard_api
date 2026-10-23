<?php

namespace App\Services\PayNl;

/**
 * An order created at Pay.nl: where to send the customer, and its current status.
 */
final readonly class PayNlOrder
{
    public function __construct(
        public string $id,
        public ?string $redirectUrl,
        public int $statusCode,
    ) {}
}
