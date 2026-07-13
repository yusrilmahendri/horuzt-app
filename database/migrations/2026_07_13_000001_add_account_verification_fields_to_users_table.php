<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('whatsapp_verified_at')->nullable()->after('email_verified_at');
            $table->string('verification_channel', 20)->nullable()->after('whatsapp_verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['whatsapp_verified_at', 'verification_channel']);
        });
    }
};
