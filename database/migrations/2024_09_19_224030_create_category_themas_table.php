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
        Schema::create('category_themas', function (Blueprint $table) {
            $table->id();
            $table->string('thema_video');
            $table->string('slug_video');
            $table->string('thema_website');
            $table->string('slug_website');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('category_themas');
    }
};
