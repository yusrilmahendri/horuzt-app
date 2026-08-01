<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_upgrade_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invitation_id')->nullable()->constrained('invitations')->nullOnDelete();
            $table->foreignId('package_before_id')->nullable()->constrained('paket_undangans')->nullOnDelete();
            $table->foreignId('package_after_id')->constrained('paket_undangans')->cascadeOnDelete();
            $table->string('payment_method', 20);
            $table->string('payment_status', 30);
            $table->decimal('amount', 15, 2)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['invitation_id', 'payment_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_upgrade_histories');
    }
};
