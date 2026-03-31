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
        Schema::create('attendance_scans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');  // Wedding owner
            $table->unsignedBigInteger('acara_id'); // Which event (akad/resepsi)
            $table->string('guest_name');           // From QR or manual entry
            $table->string('guest_identifier')->nullable(); // Unique ID from QR
            $table->enum('scan_type', ['qr_code', 'manual'])->default('qr_code');
            $table->timestamp('scanned_at');
            $table->unsignedBigInteger('scanned_by')->nullable(); // Committee member
            $table->text('notes')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['user_id', 'acara_id']);
            $table->index('scanned_at');
            $table->unique(['user_id', 'acara_id', 'guest_identifier'], 'unique_guest_scan');

            // Foreign keys
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('acara_id')->references('id')->on('acaras')->onDelete('cascade');
            $table->foreign('scanned_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_scans');
    }
};
