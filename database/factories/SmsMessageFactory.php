<?php

namespace Database\Factories;

use App\Models\SmsMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SmsMessage>
 */
class SmsMessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'purpose' => 'verification',
            'country_code' => '31',
            'phone' => '6'.fake()->numerify('########'),
            'message' => fake()->sentence(),
            'status' => 'sent',
            'sent_at' => now(),
        ];
    }
}
