<?php

namespace Database\Factories;

use App\Models\TipPageSetting;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TipPageSetting>
 */
class TipPageSettingFactory extends Factory
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
            'default_page' => TipPageSetting::PAGE_TIP_AND_REVIEW,
            'page_header' => 'Thank you for visiting',
            'page_title' => 'Leave a tip or a review',
            'success_header' => 'Thank you!',
            'success_message' => 'Your support means a lot.',
            'preset_amounts' => [5, 10, 15],
            'review_style' => 1,
            'is_active' => true,
        ];
    }
}
