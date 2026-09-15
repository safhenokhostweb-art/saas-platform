<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
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
            // TEST-HARNESS-DB-01: `email` was dropped from `users` by
            // 2026_05_30_000001_remove_email_from_users_and_password_reset_tokens
            // (this app authenticates by `mobile`) — the factory was never
            // updated after that migration, so every User::factory() call
            // failed with "Unknown column 'email'" against the real schema.
            'mobile' => '09' . fake()->unique()->numerify('#########'),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'is_admin' => false,
        ];
    }
}
