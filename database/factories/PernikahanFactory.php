<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\User;
use Carbon\Carbon;

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
            'nama_panggilan_pria' => 'kenif bokem',
            'nama_panggilan_wanita' => 'bila',
            'nama_lengkap_pria' => 'hanif kuswanto',
            'nama_lengkap_wanita' => 'nabila puspitod',
            'gender_pria' => 'pria',
            'gender_wanita' => 'wanita',
            'alamat' => 'Yogyakarta, kaliurang.',
            'video' => '',
            'photo_pria' => '',
            'photo_wanita' => '',
            'tgl_cerita' => Carbon::now()->format('Y-m-d'),
            'salam_pembuka' => '',
            'salam_wa_atas' => '',
            'salam_wa_bawah' => '',
        ];
    }
}
