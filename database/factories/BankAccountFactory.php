<?php

namespace Database\Factories;

use App\Models\BankAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BankAccount>
 */
class BankAccountFactory extends Factory
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
            'bank_name' => fake()->randomElement(['ING', 'Rabobank', 'ABN AMRO', 'bunq']),
            'account_name' => fake()->name(),
            'account_type' => 1,
            'account_number' => fake()->iban('NL'),
            'swift_code' => fake()->swiftBicNumber(),
        ];
    }
}
