<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\WorkingHour;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkingHour>
 */
class WorkingHourFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'purpose' => 'vcard',
            'user_id' => User::factory(),
            'day' => fake()->randomElement(WorkingHour::DAYS),
            'time_ranges' => [['from' => '09:00', 'to' => '17:00']],
            'is_active' => true,
        ];
    }
}
