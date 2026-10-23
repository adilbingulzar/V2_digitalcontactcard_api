<?php

namespace Tests\Feature\Http\Controllers\Api\V1\Me;

use App\Models\BankAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BankAccountControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_saves_the_account_and_only_shows_the_number_masked(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->putJson(route('api.v1.me.bank-account.update'), [
            'bank_name' => 'ABN AMRO', 'account_name' => 'A. de Vries', 'account_type' => 1,
            'account_number' => 'nl91 abna 0417 1643 00', 'swift_code' => 'abnanl2a',
        ])->assertOk()->assertJsonPath('data.account_number', '••••••••••••••4300');

        $account = BankAccount::sole();
        $this->assertSame('NL91ABNA0417164300', $account->account_number);
        $this->assertSame('ABNANL2A', $account->swift_code);
        $this->assertStringNotContainsString('0417164300', $this->getJson(route('api.v1.me.bank-account.show'))->getContent());
    }

    public function test_rejects_an_iban_with_a_typo(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->putJson(route('api.v1.me.bank-account.update'), [
            'bank_name' => 'ING', 'account_name' => 'A', 'account_type' => 1, 'account_number' => 'NL92ABNA0417164300',
        ])->assertUnprocessable()->assertJsonValidationErrors(['account_number' => 'This is not a valid IBAN. Please check it for typos.']);
    }

    public function test_shows_no_account_when_none_was_added(): void
    {
        BankAccount::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $this->getJson(route('api.v1.me.bank-account.show'))->assertOk()->assertJsonPath('data', null);
    }
}
