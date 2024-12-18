<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {   
        $this->call(RolesTableSeeder::class);
        $this->call(AdminTableSeeder::class);
        $this->call(UserTableSeeder::class);
        // $this->call(ThemasSeeder::class);
        // $this->call(CategoryThemasSeeder::class);
        // $this->call(JenisThemasSeeder::class);
        // $this->call(ResultThemasSeeder::class);
        // $this->call(PaketNikahSeeder::class);
        $this->call(BankSeeder::class);
        // $this->call(OrderSeeder::class);
        // $this->call(PembayaranSeeder::class);
        // $this->call(MempelaiSeeder::class);
        // $this->call(AcaraSeeder::class);
        // $this->call(PengunjungSeeder::class);
        // $this->call(QouteSeeder::class);
        // $this->call(PernikahanSeeder::class);
        // $this->call(ResultPernikahanSeeder::class);
        // $this->call(TestimoniSeeder::class);
        $this->call(BukuTamuSeeder::class);
        $this->call(UcapanSeeder::class);
        // $this->call(CeritaSeeder::class);
    }
}
