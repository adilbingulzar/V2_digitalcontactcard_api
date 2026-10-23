<?php

namespace App\Http\Requests\Business;

use App\Enums\EmployeeStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Who works at a spot and in which role. Only team members of the business.
 */
class UpdateSpotStaffRequest extends FormRequest
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
            'members' => ['present', 'array', 'max:100'],
            'members.*.user_id' => [
                'required', 'integer', 'distinct',
                Rule::exists('employees', 'user_id')->where('business_id', $businessId)->where('status', EmployeeStatus::Accepted->value),
            ],
            'members.*.business_role_id' => ['required', 'integer', Rule::exists('business_roles', 'id')->where('business_id', $businessId)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['members.*.user_id.exists' => 'Only members of your team can work at a spot.'];
    }
}
