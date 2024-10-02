<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\BukuTamu;

class BukuTamuSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        BukuTamu::factory()->count(10)->create();    
    }
}
