<?php

namespace App\Http\Requests\Me;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateBusinessRequest extends FormRequest
{
    /**
     * Only a business owner has business details to edit.
     */
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
            'name' => ['required', 'string', 'max:175'],
            'email' => ['nullable', 'string', 'email', 'max:175'],
            'address' => ['nullable', 'string', 'max:500'],
        ];
    }
}
