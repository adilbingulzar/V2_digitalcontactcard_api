<?php

namespace App\Http\Requests\Business;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * A tip-sharing agreement: the percentage of each tip per role, adding up to 100%.
 */
class SaveAgreementRequest extends FormRequest
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
        return [
            'title' => ['required', 'string', 'max:150'],
            'content' => ['nullable', 'string', 'max:20000'],
            'is_for_spots' => ['required', 'boolean'],
            'role_shares' => ['required', 'array', 'min:1'],
            'role_shares.*.role_id' => ['required', 'integer', 'distinct'],
            'role_shares.*.percent' => ['required', 'numeric', 'between:0,100'],
        ];
    }

    /**
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $ownRoleIds = $this->user()->business->roles()->pluck('id')->all();
                $shares = collect($this->input('role_shares'));

                if ($shares->contains(fn (array $share): bool => ! in_array((int) $share['role_id'], $ownRoleIds, true))) {
                    $validator->errors()->add('role_shares', 'Choose roles of your own business.');
                }

                if (abs($shares->sum(fn (array $share): float => (float) $share['percent']) - 100) > 0.001) {
                    $validator->errors()->add('role_shares', 'The shares must add up to 100%.');
                }
            },
        ];
    }

    /**
     * Shares as stored: {"<role id>": percent}.
     *
     * @return array<string, float>
     */
    public function roleShares(): array
    {
        return collect($this->validated('role_shares'))
            ->mapWithKeys(fn (array $share): array => [(string) $share['role_id'] => round((float) $share['percent'], 2)])
            ->all();
    }
}
