<?php

namespace App\Mail;

use App\Models\Withdrawal;

/**
 * Tells a user their payout was transferred.
 */
class PayoutPaidMail extends TemplatedMail
{
    public function __construct(public Withdrawal $withdrawal) {}

    public function templateName(): string
    {
        return 'payout_paid';
    }

    public function placeholders(): array
    {
        return [
            'name' => $this->withdrawal->user->name,
            'reference' => $this->withdrawal->reference,
            'amount' => number_format((float) $this->withdrawal->amount, 2, ',', '.'),
        ];
    }
}
