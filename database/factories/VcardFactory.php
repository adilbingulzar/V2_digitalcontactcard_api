<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Vcard;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vcard>
 */
class VcardFactory extends Factory
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
            'business_name' => fake()->company(),
            'business_title' => fake()->jobTitle(),
            'business_email' => fake()->companyEmail(),
            'business_address' => fake()->address(),
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
            'theme' => 1,
            'social_links' => ['https://www.instagram.com/example', 'https://www.linkedin.com/company/example'],
            'is_active' => true,
        ];
    }
}
