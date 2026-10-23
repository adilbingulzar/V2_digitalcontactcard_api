<?php

namespace App\Mail;

/**
 * Sent to people whose account an admin created (bulk sign-up), with a link to choose a password.
 */
class AccountInviteMail extends TemplatedMail
{
    public function __construct(
        public string $name,
        public ?string $businessName,
        public string $link,
        public ?string $note = null,
    ) {}

    public function templateName(): string
    {
        return 'account_invite';
    }

    public function placeholders(): array
    {
        return ['name' => $this->name, 'business' => $this->businessName ? ' for '.$this->businessName : '', 'message' => $this->note ?? '', 'link' => $this->link];
    }
}
