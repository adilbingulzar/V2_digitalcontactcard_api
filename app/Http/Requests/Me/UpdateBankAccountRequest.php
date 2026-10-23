<?php

namespace App\Http\Requests\Me;

use App\Rules\Iban;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBankAccountRequest extends FormRequest
{
    /**
     * Users manage only their own bank account; the controller uses the signed-in user.
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
            'bank_name' => ['required', 'string', 'max:100'],
            'account_name' => ['required', 'string', 'max:175'],
            'account_type' => ['required', 'integer', Rule::in([1, 2])],
            'account_number' => ['required', 'string', 'max:42', new Iban],
            'swift_code' => ['nullable', 'string', 'regex:/^[A-Za-z]{6}[A-Za-z0-9]{2}([A-Za-z0-9]{3})?$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['swift_code.regex' => 'This is not a valid BIC / SWIFT code.'];
    }

    /**
     * Store IBAN and BIC without spaces, in capitals.
     */
    protected function passedValidation(): void
    {
        $this->merge([
            'account_number' => strtoupper(str_replace(' ', '', (string) $this->input('account_number'))),
            'swift_code' => $this->filled('swift_code') ? strtoupper((string) $this->input('swift_code')) : null,
        ]);
    }
}
