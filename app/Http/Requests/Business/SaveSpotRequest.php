<?php

namespace App\Http\Requests\Business;

use App\Models\Spot;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * A spot (table, bar, terrace). Its URL name is part of its printed QR code.
 */
class SaveSpotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->business()->exists();
    }

    /**
     * An empty URL name is made from the spot name ("Table 1" becomes "table-1").
     */
    protected function prepareForValidation(): void
    {
        $slug = trim((string) $this->input('slug'));

        $this->merge(['slug' => $slug === '' ? Str::slug((string) $this->input('name')) : Str::lower($slug)]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Spot|null $spot */
        $spot = $this->route('spot');

        return [
            'name' => ['required', 'string', 'max:100'],
            'slug' => [
                'required', 'string', 'max:100', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('spots', 'slug')->where('business_id', $this->user()->business?->id)->ignore($spot?->id),
            ],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.regex' => 'Use lowercase letters, numbers and dashes.',
            'slug.unique' => 'Another spot already uses this URL name.',
        ];
    }
}
