<?php

namespace Database\Factories;

use App\Enums\AccountType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'slug' => Str::lower(Str::random(10)),
            'role' => UserRole::User,
            'account_type' => AccountType::Private,
            'status' => UserStatus::Active,
            'phone' => fake()->numerify('6########'),
            'country_code' => '31',
            'country' => 'NL',
            'accepts_individual_tips' => true,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
            'status' => UserStatus::Pending,
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Admin,
        ]);
    }

    public function business(): static
    {
        return $this->state(fn (array $attributes) => [
            'account_type' => AccountType::Business,
        ]);
    }

    /**
     * A user imported from the old app who has not logged in since: only the md5 hash is known.
     */
    public function withLegacyPassword(string $plainPassword = 'password'): static
    {
        return $this->state(fn (array $attributes) => [
            'password' => null,
            'legacy_password_md5' => md5($plainPassword),
        ]);
    }
}
