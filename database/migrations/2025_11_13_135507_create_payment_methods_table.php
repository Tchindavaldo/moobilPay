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
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('provider', ['stripe', 'paypal'])->index();
            $table->enum('type', ['card', 'bank_account', 'paypal_account'])->index();
            $table->string('provider_id')->nullable(); // Stripe customer_id, PayPal payer_id
            $table->string('external_id')->nullable(); // Stripe payment_method_id, PayPal agreement_id
            $table->json('metadata')->nullable(); // Card last4, brand, etc.
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'provider']);
            $table->index(['user_id', 'is_default']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
