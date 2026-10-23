<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\Spot;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Spot>
 */
class SpotFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = 'Table '.fake()->unique()->numberBetween(1, 999);

        return [
            'business_id' => Business::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'is_multi' => false,
            'is_active' => true,
        ];
    }
}
