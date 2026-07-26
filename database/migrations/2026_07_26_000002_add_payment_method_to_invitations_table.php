<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            if (! Schema::hasColumn('invitations', 'payment_method')) {
                $table->string('payment_method', 20)->nullable()->after('payment_status')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            if (Schema::hasColumn('invitations', 'payment_method')) {
                $table->dropColumn('payment_method');
            }
        });
    }
};
