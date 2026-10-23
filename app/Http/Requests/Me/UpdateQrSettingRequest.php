<?php

namespace App\Http\Requests\Me;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateQrSettingRequest extends FormRequest
{
    /**
     * Users edit only their own QR card; the controller uses the signed-in user.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Texts printed above and below the QR code.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'header' => ['nullable', 'string', 'max:100'],
            'title' => ['nullable', 'string', 'max:100'],
        ];
    }
}
