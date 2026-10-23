<?php

namespace App\Mail;

/**
 * The link to choose a new password.
 */
class PasswordResetMail extends TemplatedMail
{
    public function __construct(
        public string $name,
        public string $link,
        public int $validForMinutes,
    ) {}

    public function templateName(): string
    {
        return 'password_reset';
    }

    public function placeholders(): array
    {
        return ['name' => $this->name, 'link' => $this->link, 'minutes' => $this->validForMinutes];
    }
}
