<?php

namespace App\Mail;

/**
 * Email with the code a user enters to confirm their email address.
 */
class VerificationCodeMail extends TemplatedMail
{
    public function __construct(
        public string $name,
        public string $code,
        public int $validForMinutes,
    ) {}

    public function templateName(): string
    {
        return 'verification_code';
    }

    public function placeholders(): array
    {
        return ['name' => $this->name, 'code' => $this->code, 'minutes' => $this->validForMinutes];
    }
}
