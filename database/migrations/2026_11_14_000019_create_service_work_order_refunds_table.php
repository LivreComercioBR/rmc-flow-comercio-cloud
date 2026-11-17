<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_work_orders', function (Blueprint $table): void {
            $table->dateTime('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancellation_reason', 180)->nullable();
        });

        Schema::create('service_work_order_refunds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('service_work_order_id');
            $table->string('payment_method', 24);
            $table->unsignedBigInteger('amount_cents');
            $table->string('reference', 120)->nullable();
            $table->string('reason', 180);
            $table->string('request_key', 36);
            $table->unsignedBigInteger('cash_session_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('refunded_at');
            $table->timestamps();
            $table->unique(['company_id', 'request_key']);
            $table->foreign(['company_id', 'service_work_order_id'], 'swor_company_order_fk')->references(['company_id', 'id'])->on('service_work_orders');
            $table->foreign(['company_id', 'cash_session_id'], 'swor_company_cash_fk')->references(['company_id', 'id'])->on('cash_sessions');
            $table->index(['company_id', 'service_work_order_id', 'refunded_at'], 'swor_company_order_refunded_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_work_order_refunds');
        Schema::table('service_work_orders', function (Blueprint $table): void {
            $table->dropForeign(['cancelled_by']);
            $table->dropForeign(['cancelled_approved_by']);
            $table->dropColumn(['cancelled_at', 'cancelled_by', 'cancelled_approved_by', 'cancellation_reason']);
        });
    }
};
