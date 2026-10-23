<?php

namespace Tests\Feature\Models;

use App\Models\BankAccount;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BankAccountTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_account_number_is_encrypted_in_the_database(): void
    {
        $account = BankAccount::factory()->create(['account_number' => 'NL91ABNA0417164300']);

        $stored = DB::table('bank_accounts')->where('id', $account->id)->value('account_number');

        $this->assertStringNotContainsString('NL91ABNA0417164300', $stored);
        $this->assertSame('NL91ABNA0417164300', $account->refresh()->account_number);
    }

    public function test_masked_account_number_shows_only_last_four_characters(): void
    {
        $account = BankAccount::factory()->make(['account_number' => 'NL91ABNA0417164300']);

        $this->assertSame('••••••••••••••4300', $account->maskedAccountNumber());
    }

    public function test_serialized_bank_account_does_not_expose_account_number(): void
    {
        $account = BankAccount::factory()->create();

        $this->assertArrayNotHasKey('account_number', $account->toArray());
    }
}
