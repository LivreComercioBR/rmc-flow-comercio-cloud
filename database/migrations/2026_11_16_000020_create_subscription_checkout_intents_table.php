<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_checkout_intents', function (Blueprint $table): void {
            $table->id();
            $table->string('token', 64)->unique();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->string('contact_email', 190);
            $table->string('purchase_reference', 160)->unique();
            $table->string('preference_id', 120)->nullable()->index();
            $table->string('checkout_url', 1000)->nullable();
            $table->string('payment_id', 64)->nullable()->index();
            $table->string('payment_status', 32)->nullable();
            $table->unsignedInteger('expected_amount_cents');
            $table->string('status', 32)->default('pending')->index();
            $table->json('metadata')->nullable();
            $table->dateTime('expires_at')->nullable()->index();
            $table->dateTime('paid_at')->nullable();
            $table->dateTime('registered_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_checkout_intents');
    }
};
