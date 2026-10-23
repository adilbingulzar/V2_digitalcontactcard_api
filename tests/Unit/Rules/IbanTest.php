<?php

namespace Tests\Unit\Rules;

use App\Rules\Iban;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

class IbanTest extends TestCase
{
    #[TestWith(['NL91ABNA0417164300'])]
    #[TestWith(['NL91 ABNA 0417 1643 00'])]
    #[TestWith(['GB82WEST12345698765432'])]
    #[TestWith(['BE68539007547034'])]
    public function test_accepts_a_valid_iban(string $iban): void
    {
        $this->assertTrue($this->passes($iban));
    }

    #[TestWith(['NL92ABNA0417164300'])]
    #[TestWith(['NL91ABNA041716430'])]
    #[TestWith(['not an iban'])]
    public function test_rejects_an_iban_with_a_typo_or_wrong_format(string $iban): void
    {
        $this->assertFalse($this->passes($iban));
    }

    private function passes(string $value): bool
    {
        $passes = true;
        (new Iban)->validate('account_number', $value, function () use (&$passes): void {
            $passes = false;
        });

        return $passes;
    }
}
