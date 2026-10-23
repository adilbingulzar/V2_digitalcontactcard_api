<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Business>
 */
class BusinessFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'owner_id' => User::factory()->business(),
            'name' => fake()->company(),
            'email' => fake()->unique()->companyEmail(),
            'address' => fake()->address(),
            'code' => 'BIZ'.fake()->unique()->numerify('########'),
            'is_active' => true,
        ];
    }
}
