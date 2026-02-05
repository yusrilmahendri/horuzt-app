<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('buku_tamus', function (Blueprint $table) {
            $table->string('email')->nullable()->after('nama');
            $table->string('telepon', 20)->nullable()->after('email');
            $table->text('ucapan')->nullable()->after('telepon');
            $table->enum('status_kehadiran', ['hadir', 'tidak_hadir', 'ragu'])->default('ragu')->after('ucapan');
            $table->integer('jumlah_tamu')->default(1)->after('status_kehadiran');
            $table->boolean('is_approved')->default(true)->after('jumlah_tamu');
            $table->string('ip_address', 45)->nullable()->after('is_approved');
            $table->string('user_agent')->nullable()->after('ip_address');
            
            $table->index(['user_id', 'status_kehadiran']);
            $table->index(['user_id', 'is_approved']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('buku_tamus', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'status_kehadiran']);
            $table->dropIndex(['user_id', 'is_approved']);
            $table->dropIndex(['created_at']);
            
            $table->dropColumn([
                'email',
                'telepon',
                'ucapan',
                'status_kehadiran',
                'jumlah_tamu',
                'is_approved',
                'ip_address',
                'user_agent',
            ]);
        });
    }
};
