<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\PaketUndangan;

class PaketUndanganSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        PaketUndangan::insert([
            [
                'jenis_paket' => 'Paket 1',
                'name_paket' => 'Paket Silver',
                'price' => 99000,
                'masa_aktif' => 30,
                'halaman_buku' => 50,
                'kirim_wa' => true,
                'bebas_pilih_tema' => false,
                'kirim_hadiah' => false,
                'import_data' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'jenis_paket' => 'Paket 2',
                'name_paket' => 'Paket Gold',
                'price' => 199000,
                'masa_aktif' => 60,
                'halaman_buku' => 100,
                'kirim_wa' => true,
                'bebas_pilih_tema' => true,
                'kirim_hadiah' => false,
                'import_data' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'jenis_paket' => 'Paket 3',
                'name_paket' => 'Paket Platinum',
                'price' => 299000,
                'masa_aktif' => 90,
                'halaman_buku' => 200,
                'kirim_wa' => true,
                'bebas_pilih_tema' => true,
                'kirim_hadiah' => true,
                'import_data' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
