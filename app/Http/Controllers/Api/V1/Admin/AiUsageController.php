<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\AiUsageLog;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * What the AI cost per user in a month, for billing users: requests, tokens
 * and cost in USD, and every single request as a CSV file.
 */
class AiUsageController extends AdminController
{
    public function index(Request $request): JsonResponse
    {
        [$month, $logs] = $this->month($request);

        $users = (clone $logs)
            ->leftJoin('users', 'users.id', '=', 'ai_usage_logs.user_id')
            ->groupBy('ai_usage_logs.user_id', 'users.name', 'users.email')
            ->orderByDesc(DB::raw('SUM(ai_usage_logs.input_tokens + ai_usage_logs.output_tokens)'))
            ->get([
                'ai_usage_logs.user_id',
                'users.name',
                'users.email',
                DB::raw('COUNT(*) as requests'),
                DB::raw('SUM(CASE WHEN ai_usage_logs.succeeded THEN 0 ELSE 1 END) as failed'),
                DB::raw('SUM(ai_usage_logs.input_tokens) as input_tokens'),
                DB::raw('SUM(ai_usage_logs.output_tokens) as output_tokens'),
                DB::raw('SUM(ai_usage_logs.cost) as cost'),
                DB::raw('SUM(CASE WHEN ai_usage_logs.cost IS NULL THEN 1 ELSE 0 END) as unpriced'),
            ])
            ->map(fn (AiUsageLog $row): array => [
                'user_id' => $row->user_id,
                'name' => $row->getAttribute('name'),
                'email' => $row->getAttribute('email'),
                'requests' => (int) $row->getAttribute('requests'),
                'failed' => (int) $row->getAttribute('failed'),
                'input_tokens' => (int) $row->input_tokens,
                'output_tokens' => (int) $row->output_tokens,
                'cost' => number_format((float) $row->cost, 4, '.', ''),
                'unpriced' => (int) $row->getAttribute('unpriced'),
            ]);

        return response()->json([
            'data' => $users->values()->all(),
            'meta' => [
                'month' => $month->format('Y-m'),
                'totals' => [
                    'requests' => $users->sum('requests'),
                    'input_tokens' => $users->sum('input_tokens'),
                    'output_tokens' => $users->sum('output_tokens'),
                    'cost' => number_format($users->sum(fn (array $user): float => (float) $user['cost']), 4, '.', ''),
                    'unpriced' => $users->sum('unpriced'),
                ],
            ],
        ]);
    }

    /**
     * Every request of the month, one per line, as a CSV file that opens in Excel.
     */
    public function export(Request $request): StreamedResponse
    {
        [$month, $logs] = $this->month($request);
        $timezone = config('tiqs.business_timezone');

        return response()->streamDownload(function () use ($logs, $timezone): void {
            $out = fopen('php://output', 'w');
            // Byte order mark, so Excel reads the file as UTF-8.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Date', 'User ID', 'Name', 'Email', 'Feature', 'Provider', 'Model', 'Input tokens', 'Output tokens', 'Input price (USD / 1M)', 'Output price (USD / 1M)', 'Cost (USD)', 'Succeeded'], escape: '');

            $logs->with('user:id,name,email')->lazyById(500)->each(function (AiUsageLog $log) use ($out, $timezone): void {
                fputcsv($out, [
                    $log->created_at?->setTimezone($timezone)->format('Y-m-d H:i:s'),
                    $log->user_id,
                    self::safeCell($log->user?->name),
                    $log->user?->email,
                    $log->feature,
                    $log->provider,
                    $log->model,
                    $log->input_tokens,
                    $log->output_tokens,
                    $log->input_price,
                    $log->output_price,
                    $log->cost,
                    $log->succeeded ? 'yes' : 'no',
                ], escape: '');
            });

            fclose($out);
        }, 'ai-usage-'.$month->format('Y-m').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * The chosen month (default: this month) in the business time zone, and its usage logs.
     *
     * @return array{0: CarbonImmutable, 1: Builder<AiUsageLog>}
     */
    private function month(Request $request): array
    {
        $request->validate(['month' => ['nullable', 'date_format:Y-m']]);
        $timezone = config('tiqs.business_timezone');
        $month = $request->filled('month')
            ? CarbonImmutable::createFromFormat('Y-m-d', $request->string('month').'-01', $timezone)->startOfMonth()
            : CarbonImmutable::now($timezone)->startOfMonth();

        $logs = AiUsageLog::query()
            ->where('ai_usage_logs.created_at', '>=', $month->utc())
            ->where('ai_usage_logs.created_at', '<', $month->addMonth()->utc());

        return [$month, $logs];
    }

    /**
     * A value starting with = + - @ would run as a formula in Excel, so it is prefixed with a quote.
     */
    private static function safeCell(?string $value): ?string
    {
        return $value !== null && preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }
}
