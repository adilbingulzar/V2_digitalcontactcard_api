<?php

namespace Database\Factories;

use App\Models\QrSetting;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QrSetting>
 */
class QrSettingFactory extends Factory
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
            'header' => 'Scan to tip',
            'title' => 'Thank you!',
            'is_active' => true,
        ];
    }
}
