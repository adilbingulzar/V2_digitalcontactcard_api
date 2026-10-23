<?php

namespace App\Mail;

use App\Models\Withdrawal;

/**
 * Tells the admin that a user asked for a payout.
 */
class PayoutRequestedMail extends TemplatedMail
{
    public function __construct(public Withdrawal $withdrawal) {}

    public function templateName(): string
    {
        return 'payout_requested';
    }

    public function placeholders(): array
    {
        return [
            'name' => $this->withdrawal->user->name,
            'email' => $this->withdrawal->user->email,
            'reference' => $this->withdrawal->reference,
            'amount' => number_format((float) $this->withdrawal->amount, 2, ',', '.'),
            'fee' => number_format((float) $this->withdrawal->fee, 2, ',', '.'),
            'requested_at' => $this->withdrawal->requested_at?->setTimezone(config('tiqs.business_timezone'))->format('d-m-Y H:i'),
        ];
    }
}
