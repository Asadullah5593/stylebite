<?php

namespace Database\Factories;

use App\Models\Memory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Memory>
 */
class MemoryFactory extends Factory
{
    protected $model = Memory::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(3),
            'short_title' => fake()->words(2, true),
            'short_description' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'memory_date' => fake()->date(),
            'location_name' => fake()->city(),
            'city' => fake()->city(),
            'country' => fake()->country(),
            'visibility' => 'public',
            'status' => 'active',
        ];
    }
}
