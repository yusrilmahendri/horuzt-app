<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Themas>
 */
class ThemasFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {   
        return [
            'name' => 'pernikahan dini',
            'status' => ['aktif', 'tidak aktif'][array_rand(['aktif', 'tidak aktif'])],
        ];
    }
}
