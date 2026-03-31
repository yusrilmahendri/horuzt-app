<?php

use App\Models\MetodeTransaction;
use App\Models\PaketUndangan;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Delete Tripay payment method from metode_transactions
        MetodeTransaction::where('name', 'Tripay')->delete();

        // Add Trial package to paket_undangans
        PaketUndangan::create([
            'jenis_paket' => 'Paket Trial',
            'name_paket' => 'Paket Trial',
            'price' => 0,
            'masa_aktif' => 3,
            'halaman_buku' => 0,
            'kirim_wa' => 0,
            'bebas_pilih_tema' => 0,
            'kirim_hadiah' => 0,
            'import_data' => 1,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove Trial package
        PaketUndangan::where('name_paket', 'Paket Trial')->delete();

        // Restore Tripay payment method (you may need to adjust the ID)
        MetodeTransaction::create([
            'name' => 'Tripay',
        ]);
    }
};
