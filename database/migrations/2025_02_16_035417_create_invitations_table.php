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
        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('paket_undangan_id');
            $table->enum('status', ['step1', 'step2', 'step3', 'step4', 'completed'])->default('step1');
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('paket_undangan_id')->references('id')->on('paket_undangans');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invitations');
    }
};
