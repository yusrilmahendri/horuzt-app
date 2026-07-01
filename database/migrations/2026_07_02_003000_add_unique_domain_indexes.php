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
        Schema::table('settings', function (Blueprint $table) {
            $table->unique('domain', 'settings_domain_unique');
        });

        if (Schema::hasColumn('invitations', 'domain')) {
            Schema::table('invitations', function (Blueprint $table) {
                $table->unique('domain', 'invitations_domain_unique');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('settings')) {
            Schema::table('settings', function (Blueprint $table) {
                $table->dropUnique('settings_domain_unique');
            });
        }

        if (Schema::hasTable('invitations') && Schema::hasColumn('invitations', 'domain')) {
            Schema::table('invitations', function (Blueprint $table) {
                $table->dropUnique('invitations_domain_unique');
            });
        }
    }
};
