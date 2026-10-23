<?php

namespace App\Http\Requests\Me;

use Illuminate\Contracts\Validation\ValidationRule;

/**
 * One or two pictures (front and back) or a PDF of the user's own business card,
 * plus the card as the builder shows it, to fill the card builder from.
 */
class ImportOwnCardRequest extends AskCardAssistantRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['message'], $rules['attachment']);

        return [
            ...$rules,
            'files' => ['required', 'array', 'min:1', 'max:2'],
            'files.*' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,gif,pdf', 'max:10240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'files.required' => 'Choose a picture or PDF of your card.',
            'files.max' => 'Choose at most two files: the front and the back.',
            'files.*.mimes' => 'Choose a picture (JPG, PNG, WebP) or a PDF.',
            'files.*.max' => 'A file can be at most 10 MB.',
        ];
    }
}
