<?php

namespace App\Http\Requests\Public;

use App\Models\Spot;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A tip and, optionally, the rating and review that come with it.
 */
class TipCheckoutRequest extends FormRequest
{
    /**
     * Tips are given by anonymous customers.
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
        return [
            'amount' => [
                'required', 'numeric', 'decimal:0,2',
                'min:'.config('tiqs.tips.min_amount'),
                'max:'.config('tiqs.tips.max_amount'),
            ],
            'payment_method_id' => ['required', 'integer', Rule::in(array_keys($this->feePayer()->offeredPaymentMethods()))],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'name' => ['nullable', 'string', 'max:100'],
            'rating' => ['nullable', 'integer', 'between:1,5'],
            'review' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * The user whose payment methods and fee settings apply: the person tipped,
     * or the business owner for a tip at a spot.
     */
    public function feePayer(): User
    {
        $spot = $this->route('spot');

        return $spot instanceof Spot ? $spot->business->owner : $this->route('user');
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'payment_method_id.in' => 'This payment method is not available.',
        ];
    }
}
