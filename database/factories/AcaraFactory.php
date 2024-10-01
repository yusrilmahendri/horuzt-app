<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Acara>
 */
class AcaraFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nama_acara' => fake()->name(),
            'waktu_mulai' => fake()->name(),
            'tanggal_acara' => fake()->name(),
            'set_countDown' => fake()->name(),
            'alamat' => fake()->name(),
            'maps' => fake()->name(),
        ];
    }
}
