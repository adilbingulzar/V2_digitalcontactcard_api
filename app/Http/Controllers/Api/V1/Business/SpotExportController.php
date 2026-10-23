<?php

namespace App\Http\Controllers\Api\V1\Business;

use App\Enums\PaymentStatus;
use App\Http\Requests\Me\ReportPeriodRequest;
use App\Models\Payment;
use App\Models\Spot;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Spreadsheet downloads of the old "Manage spots" and "Spot collection" pages, as CSV.
 */
class SpotExportController extends BusinessController
{
    /**
     * Every spot with the addresses its QR codes open, e.g. to have cards printed.
     */
    public function spots(Request $request): StreamedResponse
    {
        $business = $this->business($request);
        $site = rtrim((string) config('tiqs.frontend_url'), '/');
        $spots = Spot::query()->whereBelongsTo($business)->withCount('spotEmployees as staff_count')->orderBy('name')->get();

        return $this->csv('spots', ['Spot', 'Tip page', 'Review page', 'Accepts tips', 'Staff'], $spots->map(fn (Spot $spot): array => [
            $spot->name,
            $site.'/paytips/spot/'.$business->id.'/'.$spot->slug,
            $site.'/review/spot/'.$business->id.'/'.$spot->slug,
            $spot->is_active ? 'yes' : 'no',
            $spot->staff_count,
        ])->all());
    }

    /**
     * Tips given at the business's spots in a period: one line per payment.
     */
    public function tips(ReportPeriodRequest $request): StreamedResponse
    {
        $business = $this->business($request);
        $range = $request->range();

        $payments = Payment::query()
            ->whereNotNull('spot_id')
            ->whereHas('spot', fn ($spots) => $spots->whereBelongsTo($business))
            ->where('status', $request->input('status', PaymentStatus::Paid->value))
            ->when($range !== null, fn ($query) => $query->whereBetween('created_at', $range))
            ->with('spot:id,name', 'tips:id,payment_id,rating,review,reviewer_name')
            ->orderBy('created_at')
            ->get();

        return $this->csv('spot-tips', ['Date', 'Spot', 'Tip', 'Paid by customer', 'Status', 'Rating', 'Review', 'Name'], $payments->map(function (Payment $payment): array {
            $review = $payment->tips->first(fn ($tip): bool => filled($tip->review) || $tip->rating !== null);

            return [
                $payment->created_at?->setTimezone(config('tiqs.business_timezone'))->format('Y-m-d H:i'),
                $payment->spot?->name,
                $payment->tip_amount,
                $payment->total_amount,
                $payment->status->value,
                $review?->rating,
                $review?->review,
                $review?->reviewer_name,
            ];
        })->all());
    }

    /**
     * @param  list<string>  $header
     * @param  list<array<int, mixed>>  $rows
     */
    private function csv(string $name, array $header, array $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $rows): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $header, escape: '');
            foreach ($rows as $row) {
                fputcsv($out, array_map(fn (mixed $value): mixed => is_string($value) && preg_match('/^[=+\-@]/', $value) ? "'".$value : $value, $row), escape: '');
            }
            fclose($out);
        }, $name.'-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
