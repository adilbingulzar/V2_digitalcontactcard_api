<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\VcardTheme;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VcardTheme>
 */
class VcardThemeFactory extends Factory
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
            'primary_background' => '#ffffff',
            'secondary_background' => '#f5f5f5',
            'primary_text' => '#111111',
            'secondary_text' => '#555555',
            'primary_button' => '#f97316',
            'secondary_button' => '#111111',
            'primary_button_text' => '#ffffff',
            'secondary_button_text' => '#ffffff',
            'border' => '#e5e5e5',
            'return_button' => '#f97316',
            'is_active' => true,
        ];
    }
}
