<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\User;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Pernikahan>
 */
class PernikahanFactory extends Factory
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
            'nama_panggilan_pria' => fake()->name(),
            'nama_panggilan_wanita' => fake()->name(),
            'nama_lengkap_pria' => fake()->name(),
            'nama_lengkap_wanita' => fake()->name(),
            'gender_pria' => 'pria',
            'gender_wanita' => 'wanita',
            'alamat' => fake()->name(),
            'video' => fake()->name(),
            'photo_pria' => fake()->name(),
            'photo_wanita' => fake()->name(),
            'tgl_cerita' => fake()->name(),
            'salam_pembuka' => fake()->name(),
            'salam_wa_atas' => fake()->name(),
            'salam_wa_bawah' => fake()->name(),
        ];
    }
}
