<?php

namespace App\Http\Requests\Me;

use App\Models\TipPageSetting;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTipPageRequest extends FormRequest
{
    public const array BUTTON_STYLES = ['orange', 'red', 'blue', 'light', 'dark'];

    private const string HEX_COLOR = '/^#[0-9A-Fa-f]{6}$/';

    /**
     * Users edit only their own page; the controller uses the signed-in user.
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
        $color = ['nullable', 'string', 'regex:'.self::HEX_COLOR];

        return [
            'mode' => ['required', Rule::in(['review', 'tip', 'tip_and_review'])],
            'header' => ['nullable', 'string', 'max:150'],
            'title' => ['nullable', 'string', 'max:150'],
            'success_header' => ['nullable', 'string', 'max:150'],
            'success_message' => ['nullable', 'string', 'max:500'],
            'page_color' => $color,
            'panel_color' => $color,
            'text_color' => $color,
            'emoji_color' => $color,
            'button_style' => ['required', Rule::in(self::BUTTON_STYLES)],
            'emoji_type' => ['required', 'integer', Rule::in([0, 1])],
            'show_email_field' => ['required', 'boolean'],
            'show_review_field' => ['required', 'boolean'],
            'pin_reviews' => ['sometimes', 'boolean'],
            'review_style' => ['sometimes', 'integer', Rule::in([1, 2])],
            'pattern' => ['sometimes', 'nullable', Rule::in(TipPageSetting::PATTERNS)],
            'payment_methods' => ['sometimes', 'array'],
            'payment_methods.*' => ['integer', Rule::in(array_keys(array_filter(config('payment_methods'), fn (array $method): bool => $method['public'])))],
            'preset_amounts' => ['required', 'array', 'size:3'],
            'preset_amounts.*' => [
                'required', 'numeric', 'decimal:0,2',
                'min:'.config('tiqs.tips.min_amount'),
                'max:'.config('tiqs.tips.max_amount'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'page_color.regex' => 'Use a colour code like #F97316.',
            'panel_color.regex' => 'Use a colour code like #F97316.',
            'text_color.regex' => 'Use a colour code like #F97316.',
            'emoji_color.regex' => 'Use a colour code like #F97316.',
        ];
    }
}
