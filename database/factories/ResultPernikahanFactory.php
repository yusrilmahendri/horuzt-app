<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\User;
use App\Models\Mempelai;
use App\Models\Acara;
use App\Models\Pengujung;
use App\Models\Qoute;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ResultPernikahan>
 */
class ResultPernikahanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' =>  User::inRandomOrder()->first()->id,
            'mempelai_id' =>  Mempelai::inRandomOrder()->first()->id,
            'acara_id' => Acara::inRandomOrder()->first()->id,
            'pengunjung_id' => Pengujung::inRandomOrder()->first()->id,
            'qoute_id' => Qoute::inRandomOrder()->first()->id,
        ];
    }
}
