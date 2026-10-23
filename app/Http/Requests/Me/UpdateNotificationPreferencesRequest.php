<?php

namespace App\Http\Requests\Me;

use App\Actions\Notifications\NotifyUser;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateNotificationPreferencesRequest extends FormRequest
{
    /**
     * Users manage only their own preferences; the controller uses the signed-in user.
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
            'preferences' => ['required', 'array'],
            'preferences.*.event' => ['required', 'string', 'distinct', Rule::in(array_keys(NotifyUser::EVENTS))],
            'preferences.*.enabled' => ['required', 'boolean'],
            'preferences.*.stars' => ['nullable', 'array'],
            'preferences.*.stars.*' => ['integer', 'between:1,5', 'distinct'],
        ];
    }
}
