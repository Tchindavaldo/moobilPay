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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('payment_method_id')->nullable()->constrained()->onDelete('set null');
            $table->enum('provider', ['stripe', 'paypal'])->index();
            $table->string('provider_payment_id')->nullable(); // Stripe payment_intent_id, PayPal order_id
            $table->string('provider_customer_id')->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('EUR');
            $table->enum('status', ['pending', 'processing', 'succeeded', 'failed', 'canceled', 'refunded'])->index();
            $table->enum('type', ['payment', 'refund', 'subscription'])->default('payment');
            $table->string('description')->nullable();
            $table->json('metadata')->nullable();
            $table->json('provider_response')->nullable();
            $table->decimal('fee_amount', 8, 2)->nullable();
            $table->decimal('net_amount', 10, 2)->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('failure_reason')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'status']);
            $table->index(['provider', 'status']);
            $table->index(['created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
