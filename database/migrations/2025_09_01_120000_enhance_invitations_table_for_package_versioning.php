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
        Schema::table('invitations', function (Blueprint $table) {
            // Payment status tracking
            $table->enum('payment_status', ['pending', 'paid'])->default('pending')->after('status');
            
            // Domain expiry management
            $table->datetime('domain_expires_at')->nullable()->after('payment_status');
            $table->datetime('payment_confirmed_at')->nullable()->after('domain_expires_at');
            
            // Package snapshot for version control - users keep original package terms
            $table->decimal('package_price_snapshot', 10, 2)->nullable()->after('payment_confirmed_at');
            $table->integer('package_duration_snapshot')->nullable()->after('package_price_snapshot');
            $table->json('package_features_snapshot')->nullable()->after('package_duration_snapshot');
            
            // Add index for better query performance
            $table->index(['payment_status', 'domain_expires_at']);
            $table->index(['user_id', 'payment_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            $table->dropIndex(['payment_status', 'domain_expires_at']);
            $table->dropIndex(['user_id', 'payment_status']);
            
            $table->dropColumn([
                'payment_status',
                'domain_expires_at', 
                'payment_confirmed_at',
                'package_price_snapshot',
                'package_duration_snapshot',
                'package_features_snapshot'
            ]);
        });
    }
};