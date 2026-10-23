<?php

namespace App\Http\Requests\Me;

use App\Enums\PaymentStatus;
use App\Enums\TipType;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filters of the signed-in user's reports: a period (the old app's choices,
 * plus a custom range), and optionally a payment status and tip type.
 */
class ReportPeriodRequest extends FormRequest
{
    public const array PERIODS = ['today', 'yesterday', 'this_week', 'last_week', 'this_month', 'last_month', 'all', 'custom'];

    /**
     * Each user only ever sees their own data; the controller scopes the query.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'period' => ['nullable', Rule::in(self::PERIODS)],
            'from' => ['required_if:period,custom', 'nullable', 'date_format:Y-m-d'],
            'until' => ['required_if:period,custom', 'nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'status' => ['nullable', Rule::enum(PaymentStatus::class)],
            'type' => ['nullable', Rule::enum(TipType::class)],
        ];
    }

    /**
     * Start (inclusive) and end (exclusive) of the period in UTC, or null for all time.
     * Days start at midnight in the business timezone.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}|null
     */
    public function range(): ?array
    {
        $timezone = config('tiqs.business_timezone');
        $today = CarbonImmutable::now($timezone)->startOfDay();

        $range = match ($this->input('period', 'this_month')) {
            'today' => [$today, $today->addDay()],
            'yesterday' => [$today->subDay(), $today],
            'this_week' => [$today->startOfWeek(), $today->addDay()],
            'last_week' => [$today->startOfWeek()->subWeek(), $today->startOfWeek()],
            'this_month' => [$today->startOfMonth(), $today->addDay()],
            'last_month' => [$today->startOfMonth()->subMonth(), $today->startOfMonth()],
            'custom' => [
                CarbonImmutable::parse($this->input('from'), $timezone)->startOfDay(),
                CarbonImmutable::parse($this->input('until'), $timezone)->startOfDay()->addDay(),
            ],
            default => null,
        };

        return $range === null ? null : [$range[0]->utc(), $range[1]->utc()];
    }
}
