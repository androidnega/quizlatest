<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
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
     * Students have no email; other roles get a unique address for login / verification tests.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (User $user): void {
            if ($user->role === 'student') {
                $user->email = null;
                $user->email_verified_at = null;

                return;
            }
            if ($user->email === null || $user->email === '') {
                $user->email = 'factory-'.Str::uuid().'@example.com';
            }
        });
    }

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => null,
            'index_number' => fake()->unique()->bothify('IDX/####/???'),
            'role' => 'student',
            'is_active' => true,
            'email_verified_at' => now(),
            'student_onboarded_at' => now(),
            'password' => static::$password ??= 'password',
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
        ]);
    }

    /**
     * System super administrator (same powers as any user with `is_super_admin` in the database).
     */
    public function superAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'admin',
            'is_super_admin' => true,
            'index_number' => null,
            'student_onboarded_at' => null,
        ]);
    }
}
