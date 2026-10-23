<?php

namespace Tests\Feature\Http\Controllers\Api\V1\Admin;

use App\Enums\WithdrawalStatus;
use App\Mail\PayoutPaidMail;
use App\Models\AppSetting;
use App\Models\BankAccount;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\Withdrawal;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PayoutControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->admin()->create());
    }

    private function openPayout(string $amount, string $iban = 'NL91ABNA0417164300', string $name = 'Anna de Vries'): Withdrawal
    {
        $user = User::factory()->create(['name' => $name]);
        BankAccount::factory()->for($user)->create(['account_name' => $name, 'account_number' => $iban, 'swift_code' => 'ABNANL2A']);

        return Withdrawal::factory()->for($user)->create(['amount' => $amount, 'fee' => '0.30']);
    }

    public function test_lists_open_payouts_with_totals(): void
    {
        $this->openPayout('10.00');
        $this->openPayout('5.50');
        Withdrawal::factory()->paid()->create();

        $this->getJson(route('api.v1.admin.payouts.index'))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.totals.amount', '15.50')
            ->assertJsonPath('data.0.bank_account.account_number', '••••••••••••••4300');
    }

    public function test_marks_payouts_paid_once_and_tells_the_users(): void
    {
        Mail::fake();
        $open = $this->openPayout('10.00');
        $alreadyPaid = Withdrawal::factory()->paid()->create();

        $this->postJson(route('api.v1.admin.payouts.mark-paid'), ['ids' => [$open->id, $alreadyPaid->id]])
            ->assertOk()
            ->assertJsonPath('data.marked', 1);

        $this->assertSame(WithdrawalStatus::Paid, $open->fresh()->status);
        $this->assertNotNull($open->fresh()->paid_at);
        $this->assertSame(1, UserNotification::where('user_id', $open->user_id)->where('type', 'payment_transfer')->count());
        Mail::assertQueued(PayoutPaidMail::class, fn (PayoutPaidMail $mail): bool => $mail->hasTo($open->user->email));
        Mail::assertQueuedCount(1);
    }

    public function test_exports_a_sepa_file_for_the_bank(): void
    {
        AppSetting::current()->update(['payout_debtor_name' => 'TIQS B.V.', 'payout_debtor_iban' => 'NL02RABO0123456789', 'payout_debtor_bic' => 'RABONL2U']);
        $first = $this->openPayout('10.00', 'NL91ABNA0417164300', 'Zoë Müller');
        $second = $this->openPayout('5.50', 'NL20INGB0001234567');

        $response = $this->post(route('api.v1.admin.payouts.export'), ['ids' => [$first->id, $second->id], 'format' => 'sepa'], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8');

        $xml = simplexml_load_string($response->getContent());
        $xml->registerXPathNamespace('p', 'urn:iso:std:iso:20022:tech:xsd:pain.001.001.03');
        $this->assertSame('15.50', (string) $xml->xpath('//p:GrpHdr/p:CtrlSum')[0]);
        $this->assertSame('NL02RABO0123456789', (string) $xml->xpath('//p:DbtrAcct/p:Id/p:IBAN')[0]);
        $this->assertSame(['NL91ABNA0417164300', 'NL20INGB0001234567'], array_map('strval', $xml->xpath('//p:CdtrAcct/p:Id/p:IBAN')));
        $this->assertSame('Zoe Muller', (string) $xml->xpath('//p:Cdtr/p:Nm')[0]);
        $this->assertSame($first->reference, (string) $xml->xpath('//p:EndToEndId')[0]);
    }

    public function test_sepa_export_needs_the_paying_account_and_bank_accounts(): void
    {
        $payout = $this->openPayout('10.00');

        $this->postJson(route('api.v1.admin.payouts.export'), ['ids' => [$payout->id], 'format' => 'sepa'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ids' => 'Enter the account payouts are paid from under Settings first.']);

        AppSetting::current()->update(['payout_debtor_name' => 'TIQS B.V.', 'payout_debtor_iban' => 'NL02RABO0123456789']);
        $withoutBank = Withdrawal::factory()->create();

        $this->postJson(route('api.v1.admin.payouts.export'), ['ids' => [$payout->id, $withoutBank->id], 'format' => 'sepa'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ids');
    }

    public function test_exports_csv(): void
    {
        $payout = $this->openPayout('10.00');

        $csv = $this->post(route('api.v1.admin.payouts.export'), ['ids' => [$payout->id], 'format' => 'csv'], ['Accept' => 'application/json'])
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('NL91ABNA0417164300', $csv);
        $this->assertStringContainsString($payout->reference, $csv);
    }
}
