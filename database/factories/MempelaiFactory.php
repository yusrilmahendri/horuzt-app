<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Arr;


/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Mempelai>
 */
class MempelaiFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {   
        $genders = ['wanita', 'pria'];
        return [
            'name' => fake()->name(),
            'gender' => Arr::random($genders),
        ];
    }
}
