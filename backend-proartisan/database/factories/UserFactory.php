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
            'email' => fake()->unique()->safeEmail(),
            'phone' => '07' . fake()->unique()->numerify('########'),
            'name' => fake()->name(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => 'client',
            'kyc_status' => 'en_attente',
            'score_prosartisan' => 0,
            'wallet_materiaux' => 0,
            'wallet_mo' => 0,
            'payment_phone' => '22507' . fake()->unique()->numerify('#######'),
            'preferred_payment_provider' => 'wave',
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => []);
    }
}
