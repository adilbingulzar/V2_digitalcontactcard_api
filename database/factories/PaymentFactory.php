<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'purpose' => 'tip',
            'recipient_id' => User::factory(),
            'pay_order_id' => fake()->unique()->uuid(),
            'pay_method_id' => 10,
            'status' => PaymentStatus::Pending,
            'tip_amount' => 5,
            'platform_fee' => 0,
            'method_fee' => 0.25,
            'total_amount' => 5.25,
            'currency' => 'EUR',
            'ip_address' => fake()->ipv4(),
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentStatus::Paid,
            'pay_status_code' => 100,
            'paid_at' => now(),
        ]);
    }
}
