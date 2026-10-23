<?php

namespace Database\Factories;

use App\Enums\WithdrawalStatus;
use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Withdrawal>
 */
class WithdrawalFactory extends Factory
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
            'reference' => Str::upper(Str::random(20)),
            'type' => 0,
            'amount' => fake()->randomFloat(2, 5, 300),
            'fee' => 0,
            'status' => WithdrawalStatus::Requested,
            'requested_at' => now(),
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => WithdrawalStatus::Paid,
            'paid_at' => now(),
        ]);
    }
}
