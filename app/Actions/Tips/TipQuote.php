<?php

namespace App\Actions\Tips;

/**
 * What a customer pays for a tip, in cents, and how much of the platform fee
 * is taken from the recipient instead.
 */
final readonly class TipQuote
{
    public function __construct(
        public int $tipCents,
        public int $platformFeeCents,
        public bool $customerPaysPlatformFee,
        public int $methodFeeCents,
    ) {}

    /**
     * Platform fee added to the customer's total (zero when the recipient pays it).
     */
    public function customerPlatformFeeCents(): int
    {
        return $this->customerPaysPlatformFee ? $this->platformFeeCents : 0;
    }

    /**
     * Platform fee deducted from the recipient's share (zero when the customer pays it).
     */
    public function recipientPlatformFeeCents(): int
    {
        return $this->customerPaysPlatformFee ? 0 : $this->platformFeeCents;
    }

    public function totalCents(): int
    {
        return $this->tipCents + $this->customerPlatformFeeCents() + $this->methodFeeCents;
    }

    /**
     * @return array{tip: string, platform_fee: string, method_fee: string, total: string, platform_fee_paid_by: string}
     */
    public function toArray(): array
    {
        return [
            'tip' => self::euros($this->tipCents),
            'platform_fee' => self::euros($this->customerPlatformFeeCents()),
            'method_fee' => self::euros($this->methodFeeCents),
            'total' => self::euros($this->totalCents()),
            'platform_fee_paid_by' => $this->customerPaysPlatformFee ? 'customer' : 'recipient',
        ];
    }

    public static function euros(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
