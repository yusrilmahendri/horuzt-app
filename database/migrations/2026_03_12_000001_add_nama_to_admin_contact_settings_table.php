<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('admin_contact_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('admin_contact_settings', 'nama')) {
                $table->string('nama')->nullable()->after('whatsapp');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admin_contact_settings', function (Blueprint $table) {
            $table->dropColumn('nama');
        });
    }
};
