<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Http\Controllers\Controller;
use App\Http\Requests\Me\ReportPeriodRequest;
use App\Models\Tip;
use App\Models\User;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TipExportController extends Controller
{
    /**
     * The same filtered tips as the report, as a CSV file that opens in Excel.
     */
    public function show(ReportPeriodRequest $request): StreamedResponse
    {
        /** @var User $user */
        $user = $request->user();
        $query = TipController::filtered($user, $request)->with('business:id,name', 'spot:id,name')->latest('id');
        $timezone = config('tiqs.business_timezone');

        return response()->streamDownload(function () use ($query, $timezone): void {
            $out = fopen('php://output', 'w');
            // Byte order mark, so Excel reads the file as UTF-8 (names with accents, € signs).
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Date', 'Type', 'Amount', 'Currency', 'Status', 'Paid out', 'Rating', 'Review', 'Name', 'Business', 'Spot'], escape: '');

            $query->lazyById(500)->each(function (Tip $tip) use ($out, $timezone): void {
                fputcsv($out, [
                    ($tip->paid_at ?? $tip->created_at)?->setTimezone($timezone)->format('Y-m-d H:i'),
                    $tip->type->value,
                    $tip->amount,
                    $tip->currency,
                    $tip->payment_status->value,
                    $tip->withdrawal_id !== null ? 'yes' : 'no',
                    $tip->rating,
                    $this->safeCell($tip->review),
                    $this->safeCell($tip->reviewer_name),
                    $tip->business?->name,
                    $tip->spot?->name,
                ], escape: '');
            });

            fclose($out);
        }, 'tips-'.now($timezone)->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Customers write reviews and names; a value starting with = + - @ would run
     * as a formula when the file is opened in Excel, so it is prefixed with a quote.
     */
    private function safeCell(?string $value): ?string
    {
        return $value !== null && preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }
}
