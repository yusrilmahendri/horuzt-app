<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Carbon\Carbon;

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
            'nama_acara' => 'Pernikahan nevan leksono',
            'waktu_mulai' => Carbon::now()->format('H:i:s'),
            'tanggal_acara' => Carbon::now()->format('Y-m-d'),
            'set_countDown' => '',
            'alamat' => 'Jl, Kaliurang. Yogyakarta.',
            'maps' => 'https://maps.app.goo.gl/3ZiK9U3E9toMQTM69',
        ];
    }
}
