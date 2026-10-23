<?php

namespace App\Http\Requests\Me;

use App\Services\Ai\CardAssistant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AskCardAssistantRequest extends FormRequest
{
    /**
     * Users only ever chat about their own card; the controller uses the signed-in user.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * With an attachment the request is a form upload, so the card arrives as JSON text.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('card'))) {
            $this->merge(['card' => json_decode($this->input('card'), true)]);
        }
    }

    /**
     * The card is what the builder shows now, saved or not. It only goes to the model as context.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'message' => ['required', 'string', 'max:2000'],
            // A picture or PDF of a card or brand to copy, read for this one answer.
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,gif,pdf', 'max:10240'],
            'card.design' => ['nullable', 'array'],
            'card' => ['nullable', 'array:'.implode(',', CardAssistant::cardFields())],
            'card.social_links' => ['nullable', 'array', 'max:10'],
            'card.social_links.*' => ['nullable', 'string', 'max:2000'],
            'card.section_order' => ['nullable', 'array', 'max:20'],
            'card.section_order.*' => ['nullable', 'string', 'max:30'],
            'card.template' => ['nullable', 'string', 'max:20'],
            'card.accent_color' => ['nullable', 'string', 'max:7'],
        ];
        foreach ([...array_keys(CardAssistant::TEXT_FIELDS), ...CardAssistant::URL_FIELDS] as $field) {
            $rules["card.{$field}"] = ['nullable', 'string', 'max:2000'];
        }
        foreach (CardAssistant::SWITCH_FIELDS as $field) {
            $rules["card.{$field}"] = ['nullable', 'boolean'];
        }

        return $rules;
    }

    /**
     * @return array<string, mixed>
     */
    public function card(): array
    {
        return array_intersect_key($this->validated('card') ?? [], array_flip(CardAssistant::cardFields()));
    }
}
