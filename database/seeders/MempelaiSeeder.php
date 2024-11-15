<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Mempelai;

class MempelaiSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Mempelai::factory()->male()->create();

        // Insert 'female' with name 'kuswanti'
        Mempelai::factory()->female()->create();
    }
}
