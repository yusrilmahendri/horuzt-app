<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            $table->boolean('is_trial')->default(true)->after('package_features_snapshot');
        });

        // Add index for performance
        Schema::table('invitations', function (Blueprint $table) {
            $table->index('is_trial');
        });
    }

    public function down(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            $table->dropIndex('invitations_is_trial_index');
            $table->dropColumn('is_trial');
        });
    }
};
