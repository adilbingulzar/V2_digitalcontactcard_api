<?php

namespace App\Http\Requests\Auth;

use App\Enums\AccountType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    /**
     * Sign-up is open to everyone.
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
            'first_name' => ['required', 'string', 'max:40'],
            'last_name' => ['required', 'string', 'max:40'],
            'email' => ['required', 'string', 'lowercase', 'email:rfc', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'account_type' => ['required', Rule::enum(AccountType::class)],
            'business_name' => ['nullable', 'string', 'max:175', Rule::requiredIf($this->input('account_type') === AccountType::Business->value)],
            'phone' => ['required', 'string', 'max:25', 'regex:/^[0-9 ]+$/'],
            'country_code' => ['required', 'string', 'max:5', 'regex:/^\+?[0-9]+$/'],
            'country' => ['required', 'string', 'max:10'],
            'referral' => ['nullable', 'string', 'max:50'],
            'accept_terms' => ['accepted'],
        ];
    }

    /**
     * Emails are stored in lower case; normalise before the unique check.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }
}
