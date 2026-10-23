<?php

namespace App\Http\Requests\Me;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    /**
     * Names that are pages of the site, so they cannot be anyone's public URL name.
     */
    public const array RESERVED_SLUGS = ['spot', 'thanks-review', 'thank-you', 'payerror', 'admin', 'dashboard', 'login', 'register', 'api'];

    /**
     * Users edit only their own profile; the controller uses the signed-in user.
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
            'name' => ['required', 'string', 'max:75'],
            'slug' => [
                'required', 'string', 'min:3', 'max:50', 'regex:/^[A-Za-z0-9]+$/',
                Rule::notIn(self::RESERVED_SLUGS),
                Rule::unique('users', 'slug')->ignore($this->user()->id),
            ],
            'phone' => ['nullable', 'string', 'max:25', 'regex:/^[0-9 ]+$/'],
            'country_code' => ['nullable', 'string', 'max:5', 'regex:/^\+?[0-9]+$/'],
            'country' => ['nullable', 'string', 'max:10'],
            'birth_date' => ['nullable', 'date_format:Y-m-d', 'before:today'],
            'address' => ['nullable', 'string', 'max:500'],
            'bio' => ['nullable', 'string', 'max:300'],
            'accepts_individual_tips' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.regex' => 'Use letters and numbers only.',
            'slug.unique' => 'This name is already taken.',
            'slug.not_in' => 'This name is reserved. Choose another one.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['slug' => 'public URL name'];
    }
}
