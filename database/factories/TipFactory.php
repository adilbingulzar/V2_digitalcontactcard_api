<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Enums\TipType;
use App\Models\Tip;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tip>
 */
class TipFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => TipType::Online,
            'recipient_id' => User::factory(),
            'amount' => fake()->randomElement([2, 3, 5, 7.5, 10]),
            'currency' => 'EUR',
            'payment_status' => PaymentStatus::Pending,
            'rating' => fake()->numberBetween(3, 5),
            'review' => fake()->optional()->sentence(),
            'reviewer_name' => fake()->firstName(),
            'reviewer_email' => fake()->optional()->safeEmail(),
            'token' => Str::random(40),
            'ip_address' => fake()->ipv4(),
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_status' => PaymentStatus::Paid,
            'pay_order_id' => fake()->unique()->numerify('##########X#####'),
            'paid_at' => now(),
        ]);
    }

    /**
     * A rating or review without a payment.
     */
    public function rating(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => TipType::Rating,
            'amount' => 0,
            'review' => fake()->sentence(),
        ]);
    }
}
