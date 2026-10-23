<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * An IBAN with a valid check number (ISO 13616 mod-97), so a typo is caught
 * before money is sent to the wrong account. Spaces are allowed.
 */
class Iban implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $iban = strtoupper(str_replace(' ', '', (string) $value));

        if (! preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]{11,30}$/', $iban) || ! self::checksumIsValid($iban)) {
            $fail('This is not a valid IBAN. Please check it for typos.');
        }
    }

    public static function checksumIsValid(string $iban): bool
    {
        // Move the country code and check digits to the end, turn letters into numbers (A = 10 … Z = 35).
        $digits = '';
        foreach (str_split(substr($iban, 4).substr($iban, 0, 4)) as $character) {
            $digits .= ctype_alpha($character) ? (string) (ord($character) - 55) : $character;
        }

        // The number is too long for an integer, so take mod 97 piece by piece.
        $remainder = 0;
        foreach (str_split($digits, 7) as $chunk) {
            $remainder = (int) ($remainder.$chunk) % 97;
        }

        return $remainder === 1;
    }
}
