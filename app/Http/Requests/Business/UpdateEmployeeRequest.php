<?php

namespace App\Http\Requests\Business;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Role and agreement of a team member. Also used when accepting a join request.
 */
class UpdateEmployeeRequest extends FormRequest
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
            'business_role_id' => ['nullable', 'integer', Rule::exists('business_roles', 'id')->where('business_id', $businessId)],
            'agreement_id' => ['nullable', 'integer', Rule::exists('agreements', 'id')->where('business_id', $businessId)->where('is_for_spots', false)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['business_role_id' => 'role', 'agreement_id' => 'agreement'];
    }
}
