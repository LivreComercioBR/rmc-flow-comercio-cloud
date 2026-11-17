<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_refunds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('sale_id');
            $table->string('payment_method', 24);
            $table->unsignedBigInteger('amount_cents');
            $table->string('reference', 120)->nullable();
            $table->string('reason', 180);
            $table->string('request_key', 36);
            $table->unsignedBigInteger('cash_session_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('refunded_at');
            $table->timestamps();
            $table->unique(['company_id', 'request_key']);
            $table->foreign(['company_id', 'sale_id'])->references(['company_id', 'id'])->on('sales');
            $table->foreign(['company_id', 'cash_session_id'])->references(['company_id', 'id'])->on('cash_sessions');
            $table->index(['company_id', 'sale_id', 'refunded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_refunds');
    }
};
