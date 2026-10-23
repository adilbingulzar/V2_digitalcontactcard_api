<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\FeeCollection;
use App\Models\Tip;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeeCollection>
 */
class FeeCollectionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'tip_id' => Tip::factory(),
            'fee_type' => 1,
            'tip_amount' => 5,
            'fee_amount' => 0.25,
            'tax_amount' => 0,
            'payment_status' => PaymentStatus::Pending,
        ];
    }
}
