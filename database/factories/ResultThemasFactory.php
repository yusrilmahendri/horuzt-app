<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\User;
use App\Models\Themas;
use App\Models\JenisThemas;


/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ResultThemas>
 */
class ResultThemasFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'thema_id' =>  Themas::inRandomOrder()->first()->id,
            'jenis_id' =>  JenisThemas::inRandomOrder()->first()->id,
            'user_id' => User::inRandomOrder()->first()->id,
        ];
    }
}
