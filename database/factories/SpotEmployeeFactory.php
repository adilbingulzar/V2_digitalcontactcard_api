<?php

namespace Database\Factories;

use App\Models\Spot;
use App\Models\SpotEmployee;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SpotEmployee>
 */
class SpotEmployeeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'spot_id' => Spot::factory(),
            'user_id' => User::factory(),
            'is_live' => false,
            'is_active' => true,
        ];
    }
}
