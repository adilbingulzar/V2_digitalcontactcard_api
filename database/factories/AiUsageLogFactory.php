<?php

namespace Database\Factories;

use App\Models\AiUsageLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiUsageLog>
 */
class AiUsageLogFactory extends Factory
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
            'feature' => AiUsageLog::FEATURE_CARD_ASSISTANT,
            'provider' => 'openai',
            'model' => 'gpt-6.1-sol',
            'input_tokens' => 2000,
            'output_tokens' => 300,
            'input_price' => '1.0000',
            'output_price' => '5.0000',
            'cost' => '0.003500',
            'succeeded' => true,
        ];
    }
}
