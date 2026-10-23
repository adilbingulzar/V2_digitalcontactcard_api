<?php

namespace Tests\Unit\Enums;

use App\Enums\PaymentStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PaymentStatusTest extends TestCase
{
    /**
     * @return array<string, array{int, PaymentStatus}>
     */
    public static function payNlCodes(): array
    {
        return [
            'paid' => [100, PaymentStatus::Paid],
            'authorised' => [95, PaymentStatus::Authorised],
            'partly captured' => [97, PaymentStatus::Authorised],
            'verify' => [85, PaymentStatus::Verify],
            'cancelled by user' => [-90, PaymentStatus::Cancelled],
            'voided after authorisation' => [-61, PaymentStatus::Cancelled],
            'expired' => [-80, PaymentStatus::Expired],
            'denied' => [-63, PaymentStatus::Denied],
            'failure' => [-60, PaymentStatus::Failed],
            'refunded' => [-81, PaymentStatus::Refunded],
            'chargeback' => [-71, PaymentStatus::Refunded],
            'initialised' => [20, PaymentStatus::Pending],
            'open' => [50, PaymentStatus::Pending],
            'legacy unpaid default' => [0, PaymentStatus::Pending],
        ];
    }

    #[DataProvider('payNlCodes')]
    public function test_maps_pay_nl_status_code_to_payment_status(int $code, PaymentStatus $expected): void
    {
        $this->assertSame($expected, PaymentStatus::fromPayNlCode($code));
    }

    public function test_pending_and_authorised_payments_are_not_final(): void
    {
        $this->assertFalse(PaymentStatus::Pending->isFinal());
        $this->assertFalse(PaymentStatus::Authorised->isFinal());
        $this->assertTrue(PaymentStatus::Paid->isFinal());
        $this->assertTrue(PaymentStatus::Expired->isFinal());
    }
}
