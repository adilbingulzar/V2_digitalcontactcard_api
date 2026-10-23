<?php

namespace App\Http\Requests\Business;

use App\Models\BusinessRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveRoleRequest extends FormRequest
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
        $business = $this->user()->business;
        /** @var BusinessRole|null $role */
        $role = $this->route('role');

        return [
            'name' => [
                'required', 'string', 'max:150',
                Rule::unique('business_roles', 'name')->where('business_id', $business?->id)->ignore($role?->id),
            ],
            'receives_tips' => ['required', 'boolean'],
            'level' => ['nullable', 'integer', 'between:1,9'],
            'parent_id' => [
                'nullable', 'integer',
                Rule::exists('business_roles', 'id')->where('business_id', $business?->id),
                Rule::notIn(array_filter([$role?->id])),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['name.unique' => 'You already have a role with this name.'];
    }
}
