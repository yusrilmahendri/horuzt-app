<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Pengunjung;

class PengunjungSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        $pengunjung = Pengunjung::create([
            'nama' => 'arif',
            'pesan' => 'selamat ya bro ku',
        ]);
        return $pengunjung;
    }
}
