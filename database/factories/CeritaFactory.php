<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\User;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Cerita>
 */
class CeritaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::inRandomOrder()->first()->id,
            'title' => 'kisah cinta si sigit',
            'lead_cerita' => 'sigit bertemu si alexsandra di sosmed ig',
            'tanggal_cerita' => $this->faker->date()
        ];
    }
}
