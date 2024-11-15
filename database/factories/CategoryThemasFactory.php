<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\CategoryThemas;

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
        $categories = [
            ['name' => 'thema_video'],
            ['name' => 'slug_video'],
            ['name' => 'thema_website'],
            ['name' => 'slug_website'],
        ];
        
        foreach ($categories as $category) {
            CategoryThemas::create($category);
        }
        
    }
}
