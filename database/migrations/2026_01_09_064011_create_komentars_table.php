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
        Schema::create('komentars', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('invitation_id');
            $table->string('nama', 255);
            $table->text('komentar'); // max 500 chars via validation
            $table->string('ip_address', 45)->nullable(); // For rate limiting
            $table->timestamps();

            // Foreign key with cascade delete
            $table->foreign('invitation_id')
                  ->references('id')
                  ->on('invitations')
                  ->onDelete('cascade');

            // Indexes for performance
            $table->index('invitation_id');
            $table->index('created_at');
            $table->index(['ip_address', 'created_at']); // For rate limiting queries
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('komentars');
    }
};
