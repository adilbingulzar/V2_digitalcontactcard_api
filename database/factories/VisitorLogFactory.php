<?php

namespace Database\Factories;

use App\Models\VisitorLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VisitorLog>
 */
class VisitorLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'page_type' => fake()->randomElement(['review', 'paytip', 'review_spot', 'paytip_spot']),
            'page_url' => fake()->url(),
            'ip_address' => fake()->ipv4(),
            'completed' => fake()->boolean(),
        ];
    }
}
