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
        Schema::create('payment_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('invitation_id')->nullable();
            $table->string('order_id', 100)->nullable();
            $table->string('midtrans_transaction_id', 100)->nullable();
            $table->enum('event_type', ['token_request', 'token_response', 'webhook_received', 'webhook_processed', 'error'])->default('token_request');
            $table->enum('transaction_status', ['pending', 'capture', 'settlement', 'challenge', 'deny', 'cancel', 'expire', 'refund', 'unknown'])->nullable();
            $table->string('payment_type', 50)->nullable();
            $table->decimal('gross_amount', 15, 2)->nullable();
            $table->text('request_payload')->nullable();
            $table->text('response_payload')->nullable();
            $table->string('signature_key', 255)->nullable();
            $table->boolean('signature_valid')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->text('error_message')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('invitation_id')->references('id')->on('invitations')->onDelete('set null');

            $table->index('order_id');
            $table->index('midtrans_transaction_id');
            $table->index(['order_id', 'event_type']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_logs');
    }
};
