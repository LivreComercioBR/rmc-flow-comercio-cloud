<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('local_license_orders', function (Blueprint $table): void {
            $table->id();
            $table->uuid('reference')->unique();
            $table->string('token', 64)->unique();
            $table->string('contact_email', 190);
            $table->unsignedInteger('amount_cents');
            $table->string('status', 32);
            $table->string('payment_id', 64)->nullable()->unique();
            $table->timestamp('payment_updated_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('local_license_orders');
    }
};

