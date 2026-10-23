<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\BusinessRole;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BusinessRole>
 */
class BusinessRoleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'name' => fake()->randomElement(['Manager', 'Waiter', 'Bartender', 'Chef', 'Host']),
            'level' => 3,
            'receives_tips' => true,
            'is_active' => true,
        ];
    }
}
