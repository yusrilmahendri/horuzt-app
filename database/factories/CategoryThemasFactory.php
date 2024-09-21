<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CategoryThemas>
 */
class CategoryThemasFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'thema_video' => fake()->name(),
            'slug_video' => fake()->name(),
            'thema_website' => fake()->name(),
            'slug_website' => fake()->name(),
        ];
    }
}
