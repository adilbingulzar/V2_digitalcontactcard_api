<?php

namespace App\Http\Requests\Business;

use Illuminate\Foundation\Http\FormRequest;

/**
 * "Create one click multiple spot" from the old app: one name, numbered
 * spots ("Table1", "Table2", … or "Table 1", "Table 2", …).
 */
class CreateSpotsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->business()->exists();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:95'],
            'count' => ['required', 'integer', 'between:1,100'],
            'space' => ['required', 'boolean'],
        ];
    }
}
