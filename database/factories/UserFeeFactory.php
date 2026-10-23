<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserFee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserFee>
 */
class UserFeeFactory extends Factory
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
            'type' => 'tip',
            'applies_to' => 'online',
            'fixed_fee' => 0,
            'percent_fee' => 5,
            'is_active' => true,
        ];
    }
}
