<?php

namespace App\Enums;

/**
 * Payment state of a tip or checkout, derived from Pay.nl status codes.
 *
 * @see https://developer.pay.nl/docs/transaction-statuses
 */
enum PaymentStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Authorised = 'authorised';
    case Verify = 'verify';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
    case Denied = 'denied';
    case Failed = 'failed';
    case Refunded = 'refunded';

    /**
     * Map a Pay.nl status code (also the codes stored by the legacy app) to a status.
     */
    public static function fromPayNlCode(int $code): self
    {
        return match ($code) {
            100 => self::Paid,
            95, 97 => self::Authorised,
            85 => self::Verify,
            -90, -61, -64 => self::Cancelled,
            -80 => self::Expired,
            -63 => self::Denied,
            -60 => self::Failed,
            -81, -82, -71 => self::Refunded,
            default => self::Pending,
        };
    }

    /**
     * Whether Pay.nl can still move the payment to another status by itself.
     */
    public function isFinal(): bool
    {
        return in_array($this, [self::Paid, self::Cancelled, self::Expired, self::Denied, self::Failed, self::Refunded], true);
    }
}
