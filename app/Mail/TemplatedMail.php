<?php

namespace App\Mail;

use App\Models\EmailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * An email whose wording admins can change (Admin › Email templates). The
 * built-in text below is used until a template with the same name is saved.
 * Placeholders like {name} are replaced with escaped values.
 */
abstract class TemplatedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @var array<string, array{label: string, placeholders: list<string>, subject: string, html: string}>
     */
    public const array TEMPLATES = [
        'verification_code' => [
            'label' => 'Email confirmation code',
            'placeholders' => ['name', 'code', 'minutes', 'app'],
            'subject' => 'Your {app} verification code',
            'html' => '<h2>Hi {name},</h2><p>Use this code to confirm your email address:</p><h2>{code}</h2><p>The code is valid for {minutes} minutes. If you didn\'t create an account, you can ignore this email.</p><p>Thanks,<br>{app}</p>',
        ],
        'password_reset' => [
            'label' => 'Password reset link',
            'placeholders' => ['name', 'link', 'minutes', 'app'],
            'subject' => 'Reset your {app} password',
            'html' => '<h2>Hi {name},</h2><p>You asked to reset your password. Open this link to choose a new one:</p><p><a href="{link}">Reset my password</a></p><p>The link is valid for {minutes} minutes. If you didn\'t ask for this, you can ignore this email.</p><p>{app}</p>',
        ],
        'payout_requested' => [
            'label' => 'Payout requested (to the admin)',
            'placeholders' => ['name', 'email', 'reference', 'amount', 'fee', 'requested_at', 'app'],
            'subject' => 'Payout requested: {reference}',
            'html' => '<h2>Payout requested</h2><p><strong>{name}</strong> ({email}) asked for a payout.</p><p>Reference: {reference}<br>To transfer: € {amount}<br>Payout fee: € {fee}<br>Requested: {requested_at}</p><p>The bank details are in the admin area.</p><p>{app}</p>',
        ],
        'payout_paid' => [
            'label' => 'Payout transferred (to the user)',
            'placeholders' => ['name', 'reference', 'amount', 'app'],
            'subject' => 'Your payout of € {amount} is on its way',
            'html' => '<h2>Hi {name},</h2><p>We transferred € {amount} to your bank account (reference {reference}). Depending on your bank it arrives within one or two working days.</p><p>{app}</p>',
        ],
        'account_invite' => [
            'label' => 'Account created by an admin',
            'placeholders' => ['name', 'business', 'message', 'link', 'app'],
            'subject' => 'Your {app} account is ready',
            'html' => '<h2>Hi {name},</h2><p>An account was created for you at {app}{business}. Choose your password to start receiving tips:</p><p>{message}</p><p><a href="{link}">Choose my password</a></p><p>The link works for one hour. After that, use "Forgot password" on the login page with this email address.</p><p>{app}</p>',
        ],
    ];

    abstract public function templateName(): string;

    /**
     * @return array<string, string|int|float|null>
     */
    abstract public function placeholders(): array;

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->fill($this->template()['subject'], escape: false));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.templated', with: ['body' => $this->fill($this->template()['html'], escape: true)]);
    }

    /**
     * @return array{subject: string, html: string}
     */
    private function template(): array
    {
        $saved = EmailTemplate::query()->where('name', $this->templateName())->where('is_active', true)->first();

        return $saved !== null
            ? ['subject' => $saved->subject, 'html' => $saved->html]
            : self::TEMPLATES[$this->templateName()];
    }

    private function fill(string $text, bool $escape): string
    {
        $values = ['app' => config('app.name'), ...$this->placeholders()];
        $replacements = [];
        foreach ($values as $key => $value) {
            $replacements['{'.$key.'}'] = $escape ? e((string) $value) : (string) $value;
        }

        return strtr($text, $replacements);
    }
}
