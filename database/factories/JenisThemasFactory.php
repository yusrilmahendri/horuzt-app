<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\CategoryThemas;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\JenisThemas>
 */
class JenisThemasFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {   
        return [
            'category_id' => CategoryThemas::inRandomOrder()->first()->id,
            'name' => fake()->name(),
            'price' => rand(1000, 10000),
            'preview' => fake()->name(),
            'url_thema' => 'https://picsum.photos',

        ];
    }
}
