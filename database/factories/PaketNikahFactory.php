<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Carbon\Carbon;

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
            'name' => 'golden',
            'price' => rand(1000, 10000),
            'masa_aktif' => Carbon::now()->format('Y-m-d'),
            'buku_tamu' => '0',
            'kirim_wa' => '1',
            'kirim_hadiah' => '1',
            'tema_bebas' => '0',
            'import_data' => 'https://picsum.photos',

        ];
    }
}
