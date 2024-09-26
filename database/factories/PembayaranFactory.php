<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Bank;
use App\Models\Order;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Pembayaran>
 */
class PembayaranFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'bank_id' =>  Bank::inRandomOrder()->first()->id,
            'order_id' =>  Order::inRandomOrder()->first()->id,
            'status' => fake()->name(),
            'nama_pemilik_rek' => fake()->name(),
            'no_rek' => fake()->name(),
            'price' => rand(1000, 10000),
            'va_number' => fake()->name(),
            'type_channel' => fake()->name(), 
        ];
    }
}
