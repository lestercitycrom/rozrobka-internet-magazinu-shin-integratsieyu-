<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\Identity\Enums\UserRole;
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
            'remember_token' => Str::random(10),
            'role' => UserRole::Customer,
            'locale' => 'nb',
            'is_active' => true,
            'last_login_at' => null,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function owner(): static
    {
        return $this->state(fn (array $attributes) => ['role' => UserRole::Owner]);
    }

    public function editor(): static
    {
        return $this->state(fn (array $attributes) => ['role' => UserRole::Editor]);
    }

    public function operations(): static
    {
        return $this->state(fn (array $attributes) => ['role' => UserRole::Operations]);
    }

    public function customer(): static
    {
        return $this->state(fn (array $attributes) => ['role' => UserRole::Customer]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }

    public function russian(): static
    {
        return $this->state(fn (array $attributes) => ['locale' => 'ru']);
    }
}
