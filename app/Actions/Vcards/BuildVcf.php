<?php

namespace App\Actions\Vcards;

use App\Services\Wallet\BusinessCard;

/**
 * Writes a business card as a vCard 3.0 file, which phones import as a contact.
 */
class BuildVcf
{
    public function handle(BusinessCard $card): string
    {
        $nameParts = preg_split('/\s+/', trim($card->name), 2) ?: [$card->name];
        $firstName = $nameParts[0];
        $lastName = $nameParts[1] ?? '';

        $lines = array_filter([
            'BEGIN:VCARD',
            'VERSION:3.0',
            'N:'.$this->escape($lastName).';'.$this->escape($firstName).';;;',
            'FN:'.$this->escape($card->name),
            $card->company ? 'ORG:'.$this->escape($card->company) : null,
            $card->title ? 'TITLE:'.$this->escape($card->title) : null,
            $card->email ? 'EMAIL;TYPE=INTERNET,WORK:'.$this->escape($card->email) : null,
            $card->phone ? 'TEL;TYPE=WORK,VOICE:'.$this->escape($card->phone) : null,
            $card->address ? 'ADR;TYPE=WORK:;;'.$this->escape($card->address).';;;;'.$this->escape((string) $card->country) : null,
            'URL:'.$this->escape($card->url),
            $card->photoUrl ? 'PHOTO;VALUE=URI:'.$card->photoUrl : null,
            'END:VCARD',
        ]);

        return implode("\r\n", array_map($this->fold(...), $lines))."\r\n";
    }

    private function escape(string $value): string
    {
        return str_replace(['\\', ';', ',', "\r\n", "\n", "\r"], ['\\\\', '\;', '\\,', '\\n', '\\n', '\\n'], trim($value));
    }

    /**
     * Lines longer than 75 bytes continue on the next line after a space (RFC 6350), without splitting a character.
     */
    private function fold(string $line): string
    {
        $folded = '';
        $length = 0;
        foreach (mb_str_split($line) as $character) {
            if ($length + strlen($character) > 75) {
                $folded .= "\r\n ";
                $length = 1;
            }
            $folded .= $character;
            $length += strlen($character);
        }

        return $folded;
    }
}
