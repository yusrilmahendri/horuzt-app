<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PaketNikah>
 */
class PaketNikahFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'price' => rand(1000, 10000),
            'masa_aktif' => fake()->name(),
            'buku_tamu' => fake()->name(),
            'kirim_wa' => fake()->name(),
            'kirim_hadiah' => fake()->name(),
            'tema_bebas' => fake()->name(),
            'import_data' => 'https://picsum.photos',

        ];
    }
}
