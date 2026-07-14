<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('wedding_guests') || Schema::hasColumn('wedding_guests', 'invitation_url')) {
            return;
        }

        Schema::table('wedding_guests', function (Blueprint $table) {
            $table->string('invitation_url', 2048)->nullable()->after('domain');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('wedding_guests') || ! Schema::hasColumn('wedding_guests', 'invitation_url')) {
            return;
        }

        Schema::table('wedding_guests', function (Blueprint $table) {
            $table->dropColumn('invitation_url');
        });
    }
};
