<?php

namespace Database\Factories;

use App\Models\AppSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AppSetting>
 */
class AppSettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tip_fee_fixed' => 0,
            'tip_fee_percent' => 0,
            'business_subscription_required' => false,
            'user_subscription_required' => false,
            'support_email' => 'support@example.com',
        ];
    }
}
