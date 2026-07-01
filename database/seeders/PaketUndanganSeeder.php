<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PaketUndangan;

class PaketUndanganSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $packages = [
            'trial' => [
                'jenis_paket' => PaketUndangan::jenisPaketFromCode('trial'),
                'name_paket' => PaketUndangan::displayLabelFromCode('trial'),
                'price' => 0,
                'masa_aktif' => 3,
                'halaman_buku' => 0,
                'kirim_wa' => false,
                'bebas_pilih_tema' => false,
                'kirim_hadiah' => false,
                'import_data' => false,
            ],
            'ruby' => [
                'jenis_paket' => PaketUndangan::jenisPaketFromCode('ruby'),
                'name_paket' => PaketUndangan::displayLabelFromCode('ruby'),
                'price' => 99000,
                'masa_aktif' => 30,
                'halaman_buku' => 50,
                'kirim_wa' => true,
                'bebas_pilih_tema' => false,
                'kirim_hadiah' => false,
                'import_data' => true,
            ],
            'sapphire' => [
                'jenis_paket' => PaketUndangan::jenisPaketFromCode('sapphire'),
                'name_paket' => PaketUndangan::displayLabelFromCode('sapphire'),
                'price' => 199000,
                'masa_aktif' => 60,
                'halaman_buku' => 100,
                'kirim_wa' => true,
                'bebas_pilih_tema' => true,
                'kirim_hadiah' => false,
                'import_data' => true,
            ],
            'diamond' => [
                'jenis_paket' => PaketUndangan::jenisPaketFromCode('diamond'),
                'name_paket' => PaketUndangan::displayLabelFromCode('diamond'),
                'price' => 299000,
                'masa_aktif' => 90,
                'halaman_buku' => 200,
                'kirim_wa' => true,
                'bebas_pilih_tema' => true,
                'kirim_hadiah' => true,
                'import_data' => true,
            ],
        ];

        foreach ($packages as $code => $attributes) {
            PaketUndangan::updateOrCreate(['code' => $code], $attributes);
        }
    }
}
