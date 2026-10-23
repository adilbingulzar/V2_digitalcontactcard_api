<?php

namespace Database\Factories;

use App\Enums\EmployeeStatus;
use App\Models\Business;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'user_id' => User::factory(),
            'requested_by' => 'business',
            'status' => EmployeeStatus::Accepted,
            'is_live' => false,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => EmployeeStatus::Pending,
        ]);
    }
}
