<?php

namespace App\Http\Requests\Me;

use App\Models\Vcard;
use App\Models\VcardTheme;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateVcardRequest extends FormRequest
{
    /**
     * Users edit only their own card; the controller uses the signed-in user.
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
        $colorRules = ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'];
        $urlRules = ['nullable', 'string', 'url:http,https', 'max:2000'];

        return [
            'is_active' => ['required', 'boolean'],
            'theme' => ['required', 'integer', Rule::in([Vcard::THEME_GREEN, Vcard::THEME_CUSTOM])],
            'template' => ['nullable', 'string', Rule::in(Vcard::TEMPLATES)],
            'accent_color' => $colorRules,
            'section_order' => ['nullable', 'array', 'max:'.count(Vcard::SECTIONS)],
            'section_order.*' => ['required', 'string', 'distinct', Rule::in(Vcard::SECTIONS)],
            // Cleaned by CardDesign: unknown parts are dropped rather than refused.
            'design' => ['nullable', 'array'],
            'show_business_name' => ['required', 'boolean'],
            'business_name' => ['nullable', 'required_if_accepted:show_business_name', 'string', 'max:255'],
            'business_title' => ['nullable', 'string', 'max:255'],
            'business_email' => ['required', 'string', 'email', 'max:255'],
            'show_location' => ['required', 'boolean'],
            'business_address' => ['nullable', 'required_if_accepted:show_location', 'string', 'max:500'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'show_opening_hours' => ['required', 'boolean'],
            'show_review' => ['required', 'boolean'],
            'show_business_icon' => ['required', 'boolean'],
            'show_email_icon' => ['required', 'boolean'],
            'show_phone_icon' => ['required', 'boolean'],
            'show_address_icon' => ['required', 'boolean'],
            'appointment_url' => $urlRules,
            'google_review_url' => $urlRules,
            'ambassador_url' => $urlRules,
            'social_links' => ['nullable', 'array', 'max:10'],
            'social_links.*' => ['required', 'string', 'url:http,https', 'max:500', 'distinct'],
            'colors' => ['nullable', 'array:'.implode(',', array_keys(VcardTheme::DEFAULTS))],
            'colors.*' => $colorRules,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'business_name.required_if_accepted' => 'Enter the business name, or show your logo instead.',
            'business_address.required_if_accepted' => 'Enter the address, or hide the location.',
            'social_links.*.url' => 'Enter a full link starting with https://',
            'colors.*.regex' => 'Choose a colour like #f97316.',
        ];
    }

    /**
     * A card that hides the business name shows the logo instead, so it needs one.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->boolean('show_business_name') && blank($this->user()->vcard?->logo)) {
                    $validator->errors()->add('show_business_name', 'Upload a logo first, or show the business name.');
                }
            },
        ];
    }
}
