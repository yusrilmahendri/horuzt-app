<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\MetodeTransaction;

class MetodeTransactionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        MetodeTransaction::insert([
            [
                'id' => 1,
                'name' => 'Manual'
            ],
            [
                'id' => 2,
                'name' => 'Tripay'
            ],
            [
                'id' => 3,
                'name' => 'Midtrans'
            ],
             [
                'id' => 4,
                'name' => 'Trial'
            ]
        ]);
    }
}
