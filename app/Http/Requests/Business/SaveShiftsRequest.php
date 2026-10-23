<?php

namespace App\Http\Requests\Business;

use App\Models\WorkingHour;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Weekly shift hours: per weekday (Mon … Sun) up to three time ranges. A range
 * whose end is before its start runs past midnight (22:00 to 02:00).
 */
class SaveShiftsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->business()->exists();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'days' => ['present', 'array:'.implode(',', WorkingHour::DAYS)],
            'days.*' => ['array', 'max:3'],
            'days.*.*.from' => ['required', 'date_format:H:i'],
            'days.*.*.to' => ['required', 'date_format:H:i'],
        ];
    }

    /**
     * @return array<string, list<array{from: string, to: string}>>
     */
    public function days(): array
    {
        $days = [];
        foreach ($this->validated('days') as $day => $ranges) {
            $days[$day] = array_values(array_map(
                fn (array $range): array => ['from' => $range['from'], 'to' => $range['to']],
                array_filter($ranges, fn (array $range): bool => $range['from'] !== $range['to']),
            ));
        }

        return $days;
    }
}
