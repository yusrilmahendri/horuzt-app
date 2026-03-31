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
        Schema::create('wedding_guests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id'); // Wedding owner
            $table->string('guest_name'); // From query param
            $table->string('guest_token', 64)->unique(); // Unique identifier for QR
            $table->string('domain'); // Wedding domain
            $table->timestamp('first_visit_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->boolean('attended')->default(false);
            $table->timestamp('attended_at')->nullable();
            $table->unsignedBigInteger('attended_acara_id')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['user_id', 'guest_token']);
            $table->index('domain');
            $table->index(['user_id', 'attended']);

            // Foreign keys
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('attended_acara_id')->references('id')->on('acaras')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wedding_guests');
    }
};
