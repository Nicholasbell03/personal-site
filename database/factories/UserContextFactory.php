<?php

namespace Database\Factories;

use App\Enums\UserContextKey;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UserContext>
 */
class UserContextFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => fake()->randomElement(UserContextKey::cases()),
            'value' => fake()->paragraphs(3, true),
        ];
    }
}
