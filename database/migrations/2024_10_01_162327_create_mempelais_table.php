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
        Schema::create('mempelais', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('gender_pria' ,['wanita', 'pria'])->nullable();
            $table->enum('gender_wanita' ,['wanita', 'pria'])->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mempelais');
    }
};
