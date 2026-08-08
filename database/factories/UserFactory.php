<?php

namespace Database\Factories;

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
            // Real usernames are /^[a-z0-9_]+$/ (enforced at registration), but
            // faker's userName() emits dots — normalise so round-tripping a
            // factory user through admin forms never trips alpha_dash rules.
            'username' => str_replace(['.', '-'], '_', strtolower(fake()->unique()->userName())),
            'email' => fake()->unique()->safeEmail(),
            'full_name' => fake()->name(),
            'email_verified_at' => now(),
            'password_hash' => static::$password ??= Hash::make('password'),
            'last_login_at' => now(),
            'last_seen_at' => now(),
            'locale' => 'en',
            'timezone' => config('app.timezone', 'UTC'),
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
     * Keep the legacy enum column and the Spatie role in step: a factory
     * user created with role => 'admin' also gets the Spatie admin role,
     * so permission-gated panel routes work in tests.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (User $user): void {
            if ($user->role && \Spatie\Permission\Models\Role::query()
                ->where('name', $user->role)
                ->where('guard_name', 'web')
                ->exists()) {
                $user->assignRole($user->role);
            }
        });
    }
}
