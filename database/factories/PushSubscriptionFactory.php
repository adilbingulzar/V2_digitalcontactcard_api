<?php

namespace Database\Factories;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PushSubscription>
 */
class PushSubscriptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $endpoint = 'https://push.example.test/send/'.fake()->uuid();

        return [
            'user_id' => User::factory(),
            'endpoint' => $endpoint,
            'endpoint_hash' => hash('sha256', $endpoint),
            'public_key' => rtrim(strtr(base64_encode("\x04".random_bytes(64)), '+/', '-_'), '='),
            'auth_token' => rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '='),
        ];
    }
}
