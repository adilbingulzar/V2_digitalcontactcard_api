<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\VerificationCode;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<VerificationCode>
 */
class VerificationCodeFactory extends Factory
{
    /**
     * Define the model's default state. The plain code is "123456".
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'purpose' => 'confirm_email',
            'code_hash' => Hash::make('123456'),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(15),
        ];
    }
}
