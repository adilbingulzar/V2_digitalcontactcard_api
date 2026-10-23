<?php

namespace App\Actions\Admin;

use App\Models\AppSetting;
use App\Models\Withdrawal;
use DOMDocument;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Writes payouts as a SEPA credit transfer file (pain.001.001.03), which
 * Dutch and other European banks import as one batch of transfers. Replaces
 * the old spreadsheet export.
 */
class BuildSepaTransfer
{
    /**
     * @param  Collection<int, Withdrawal>  $withdrawals  with user.bankAccount loaded
     *
     * @throws ValidationException when the paying account is not set up or a user has no bank account
     */
    public function handle(Collection $withdrawals): string
    {
        $settings = AppSetting::current();
        $debtorIban = $this->iban((string) $settings->payout_debtor_iban);

        if (blank($settings->payout_debtor_name) || $debtorIban === '') {
            throw ValidationException::withMessages(['ids' => 'Enter the account payouts are paid from under Settings first.']);
        }

        $missing = $withdrawals->filter(fn (Withdrawal $withdrawal): bool => $withdrawal->user?->bankAccount === null)->map(fn (Withdrawal $withdrawal): string => $withdrawal->user?->name ?? '#'.$withdrawal->user_id);
        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages(['ids' => 'No bank account for: '.$missing->implode(', ').'.']);
        }

        $total = $withdrawals->sum(fn (Withdrawal $withdrawal): int => (int) round((float) $withdrawal->amount * 100));
        $messageId = 'TIQS-'.now()->format('YmdHis').'-'.Str::upper(Str::random(6));

        $xml = new DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = true;
        $document = $xml->appendChild($xml->createElementNS('urn:iso:std:iso:20022:tech:xsd:pain.001.001.03', 'Document'));
        $initiation = $document->appendChild($xml->createElement('CstmrCdtTrfInitn'));

        $header = $initiation->appendChild($xml->createElement('GrpHdr'));
        $this->add($header, 'MsgId', $messageId);
        $this->add($header, 'CreDtTm', now()->format('Y-m-d\TH:i:s'));
        $this->add($header, 'NbOfTxs', (string) $withdrawals->count());
        $this->add($header, 'CtrlSum', $this->amount($total));
        $this->add($header->appendChild($xml->createElement('InitgPty')), 'Nm', $this->text((string) $settings->payout_debtor_name));

        $batch = $initiation->appendChild($xml->createElement('PmtInf'));
        $this->add($batch, 'PmtInfId', $messageId);
        $this->add($batch, 'PmtMtd', 'TRF');
        $this->add($batch, 'BtchBookg', 'true');
        $this->add($batch, 'NbOfTxs', (string) $withdrawals->count());
        $this->add($batch, 'CtrlSum', $this->amount($total));
        $this->add($batch->appendChild($xml->createElement('PmtTpInf'))->appendChild($xml->createElement('SvcLvl')), 'Cd', 'SEPA');
        $this->add($batch, 'ReqdExctnDt', now()->toDateString());
        $this->add($batch->appendChild($xml->createElement('Dbtr')), 'Nm', $this->text((string) $settings->payout_debtor_name));
        $this->add($batch->appendChild($xml->createElement('DbtrAcct'))->appendChild($xml->createElement('Id')), 'IBAN', $debtorIban);
        $this->agent($batch, 'DbtrAgt', (string) $settings->payout_debtor_bic);
        $this->add($batch, 'ChrgBr', 'SLEV');

        foreach ($withdrawals as $withdrawal) {
            $account = $withdrawal->user->bankAccount;
            $transfer = $batch->appendChild($xml->createElement('CdtTrfTxInf'));
            $this->add($transfer->appendChild($xml->createElement('PmtId')), 'EndToEndId', $this->text((string) $withdrawal->reference, 35));
            $amount = $this->add($transfer->appendChild($xml->createElement('Amt')), 'InstdAmt', $this->amount((int) round((float) $withdrawal->amount * 100)));
            $amount->setAttribute('Ccy', 'EUR');
            $this->agent($transfer, 'CdtrAgt', (string) $account->swift_code);
            $this->add($transfer->appendChild($xml->createElement('Cdtr')), 'Nm', $this->text($account->account_name));
            $this->add($transfer->appendChild($xml->createElement('CdtrAcct'))->appendChild($xml->createElement('Id')), 'IBAN', $this->iban((string) $account->account_number));
            $this->add($transfer->appendChild($xml->createElement('RmtInf')), 'Ustrd', $this->text(config('app.name').' tips payout '.$withdrawal->reference, 140));
        }

        return (string) $xml->saveXML();
    }

    private function add(\DOMNode $parent, string $name, string $value): \DOMElement
    {
        $element = $parent->ownerDocument->createElement($name);
        $element->appendChild($parent->ownerDocument->createTextNode($value));
        $parent->appendChild($element);

        return $element;
    }

    /**
     * Without a BIC, banks look it up from the IBAN ("NOTPROVIDED").
     */
    private function agent(\DOMNode $parent, string $name, string $bic): void
    {
        $institution = $parent->appendChild($parent->ownerDocument->createElement($name))->appendChild($parent->ownerDocument->createElement('FinInstnId'));
        $bic = strtoupper(preg_replace('/\s+/', '', $bic) ?? '');

        if (preg_match('/^[A-Z]{6}[A-Z0-9]{2}([A-Z0-9]{3})?$/', $bic) === 1) {
            $this->add($institution, 'BIC', $bic);
        } else {
            $this->add($institution->appendChild($parent->ownerDocument->createElement('Othr')), 'Id', 'NOTPROVIDED');
        }
    }

    private function amount(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    private function iban(string $iban): string
    {
        return strtoupper(preg_replace('/\s+/', '', $iban) ?? '');
    }

    /**
     * SEPA allows only basic Latin letters, digits and a few signs: accents are dropped, anything else becomes a space.
     */
    private function text(string $value, int $max = 70): string
    {
        $ascii = Str::ascii($value);
        $clean = trim(preg_replace('/\s+/', ' ', preg_replace("#[^A-Za-z0-9/\-?:().,'+ ]#", ' ', $ascii) ?? '') ?? '');

        return mb_substr($clean, 0, $max);
    }
}
