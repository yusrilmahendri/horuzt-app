<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\PaketNikah;

class PaketNikahSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Check if packages already exist to prevent duplicates
        if (PaketNikah::count() > 0) {
            $this->command->info('Paket nikah already exists. Skipping seeder.');
            return;
        }
        PaketNikah::insert(['name' => 'Ruby', 'price' => 50000, 'masa_aktif' => 30, 'buku_tamu' => 0, 'kirim_wa' => 1, 'kirim_hadiah' => 1, 'tema_bebas' => 0, 'import_data' => 1]);
        PaketNikah::insert(['name' => 'Sapphire', 'price' => 150000, 'masa_aktif' => 30, 'buku_tamu' => 0, 'kirim_wa' => 1, 'kirim_hadiah' => 1, 'tema_bebas' => 0, 'import_data' => 1]);
        PaketNikah::insert(['name' => 'Diamond', 'price' => 100000, 'masa_aktif' => 30, 'buku_tamu' => 0, 'kirim_wa' => 1, 'kirim_hadiah' => 1, 'tema_bebas' => 0, 'import_data' => 1]);
    }
}
