<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\VcardAssistantMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VcardAssistantMessage>
 */
class VcardAssistantMessageFactory extends Factory
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
            'role' => VcardAssistantMessage::ROLE_USER,
            'content' => fake()->sentence(),
        ];
    }

    public function fromAssistant(): static
    {
        return $this->state(fn (): array => [
            'role' => VcardAssistantMessage::ROLE_ASSISTANT,
            'changes' => ['template' => 'bold'],
            'provider' => 'anthropic',
            'model' => 'claude-opus-5-5',
            'input_tokens' => 1200,
            'output_tokens' => 150,
        ]);
    }
}
