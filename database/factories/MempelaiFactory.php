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
        return [
            'name' => $this->faker->name, // Random name by default
            'gender_pria' => 'pria',
            'gender_wanita' => 'wanita', // Random gender value
        ];
    }

    public function male()
    {
        return $this->state([
            'name' => 'wanto',
            'gender_pria' => 'pria',
        ]);
    }

    // State for 'female' gender with name 'kuswanti'
    public function female()
    {
        return $this->state([
            'name' => 'kuswanti',
            'gender_wanita' => 'wanita',
        ]);
    }
}
