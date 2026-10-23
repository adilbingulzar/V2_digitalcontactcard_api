<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserNotification>
 */
class UserNotificationFactory extends Factory
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
            'audience' => 'user',
            'type' => 'service_tip_self',
            'title' => 'Tip Alert',
            'message' => 'You have received a tip for giving good customer service',
            'icon' => 'fa-circle-dollar-to-slot',
        ];
    }
}
