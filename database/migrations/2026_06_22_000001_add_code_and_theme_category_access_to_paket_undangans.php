<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paket_undangans', function (Blueprint $table) {
            $table->string('code', 32)->nullable()->unique()->after('id');
        });

        Schema::create('paket_undangan_category_thema', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paket_undangan_id')
                ->constrained('paket_undangans')
                ->cascadeOnDelete();
            $table->foreignId('category_thema_id')
                ->constrained('category_themas')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(
                ['paket_undangan_id', 'category_thema_id'],
                'paket_category_thema_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paket_undangan_category_thema');

        Schema::table('paket_undangans', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn('code');
        });
    }
};
