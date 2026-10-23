<?php

namespace App\Http\Requests\Business;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Invite someone to the team by their email address or public URL name.
 */
class InviteEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->business()->exists();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $businessId = $this->user()->business?->id;

        return [
            'identifier' => ['required', 'string', 'max:255'],
            'business_role_id' => ['nullable', 'integer', Rule::exists('business_roles', 'id')->where('business_id', $businessId)],
            'agreement_id' => ['nullable', 'integer', Rule::exists('agreements', 'id')->where('business_id', $businessId)->where('is_for_spots', false)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['identifier' => 'email or public name', 'business_role_id' => 'role', 'agreement_id' => 'agreement'];
    }
}
