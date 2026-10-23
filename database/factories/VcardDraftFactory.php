<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\VcardDraft;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VcardDraft>
 */
class VcardDraftFactory extends Factory
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
            'data' => ['name' => fake()->name(), 'template' => 'modern', 'accent_color' => '#0f766e', 'design' => null],
        ];
    }
}
