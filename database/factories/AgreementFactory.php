<?php

namespace Database\Factories;

use App\Models\Agreement;
use App\Models\Business;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Agreement>
 */
class AgreementFactory extends Factory
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
            'title' => 'Tip sharing agreement',
            'content' => fake()->paragraphs(3, true),
            'role_shares' => [],
            'is_default' => false,
            'is_for_spots' => false,
            'is_active' => true,
        ];
    }
}
