<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->unsignedInteger('last_sale_number')->default(0);
        });
        Schema::table('cash_sessions', function (Blueprint $table): void {
            $table->unsignedBigInteger('opening_amount_cents')->change();
            $table->unsignedBigInteger('expected_closing_cents')->nullable()->change();
            $table->unsignedBigInteger('counted_closing_cents')->nullable()->change();
        });
        Schema::table('cash_movements', function (Blueprint $table): void {
            $table->unsignedBigInteger('amount_cents')->change();
        });
        Schema::create('sales', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedInteger('sale_number');
            $table->string('status', 16)->default('completed')->index();
            $table->unsignedBigInteger('subtotal_cents');
            $table->unsignedBigInteger('discount_cents')->default(0);
            $table->unsignedBigInteger('total_cents');
            $table->unsignedBigInteger('paid_cents')->default(0);
            $table->unsignedBigInteger('balance_cents')->default(0);
            $table->string('request_key', 36);
            $table->dateTime('sold_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancellation_reason', 180)->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'id']);
            $table->unique(['company_id', 'sale_number']);
            $table->unique(['company_id', 'request_key']);
            $table->index(['company_id', 'sold_at']);
            $table->foreign(['company_id', 'customer_id'])->references(['company_id', 'id'])->on('customers');
        });

        Schema::create('sale_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('sale_id');
            $table->unsignedBigInteger('product_id');
            $table->string('product_name', 160);
            $table->decimal('quantity', 12, 3);
            $table->unsignedBigInteger('unit_price_cents');
            $table->unsignedBigInteger('unit_cost_cents');
            $table->unsignedBigInteger('line_total_cents');
            $table->timestamps();
            $table->foreign(['company_id', 'sale_id'])->references(['company_id', 'id'])->on('sales')->cascadeOnDelete();
            $table->foreign(['company_id', 'product_id'])->references(['company_id', 'id'])->on('products');
        });

        Schema::create('sale_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('sale_id');
            $table->string('payment_method', 24);
            $table->unsignedBigInteger('amount_cents');
            $table->string('reference', 120)->nullable();
            $table->string('request_key', 36)->nullable();
            $table->dateTime('paid_at');
            $table->timestamps();
            $table->unique(['company_id', 'request_key']);
            $table->foreign(['company_id', 'sale_id'])->references(['company_id', 'id'])->on('sales')->cascadeOnDelete();
        });

        Schema::create('accounts_receivable', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('sale_id');
            $table->unsignedTinyInteger('installment_number');
            $table->unsignedBigInteger('amount_cents');
            $table->unsignedBigInteger('paid_cents')->default(0);
            $table->unsignedBigInteger('cancelled_balance_cents')->default(0);
            $table->date('due_date');
            $table->string('status', 16)->default('pending')->index();
            $table->timestamps();
            $table->unique(['company_id', 'sale_id', 'installment_number']);
            $table->unique(['company_id', 'id'], 'ar_company_id_uq');
            $table->foreign(['company_id', 'sale_id'])->references(['company_id', 'id'])->on('sales')->cascadeOnDelete();
            $table->index(['company_id', 'status', 'due_date']);
        });

        Schema::create('account_receivable_payments', function (Blueprint $table): void {
    $table->id();

    $table->foreignId('company_id')
        ->constrained()
        ->cascadeOnDelete();

    $table->unsignedBigInteger('account_receivable_id');
    $table->string('payment_method', 24);
    $table->unsignedBigInteger('amount_cents');
    $table->unsignedBigInteger('cash_session_id')->nullable();
    $table->string('reference', 120)->nullable();
    $table->string('request_key', 36);
    $table->dateTime('paid_at');

    $table->foreignId('created_by')
        ->nullable()
        ->constrained('users')
        ->nullOnDelete();

    $table->timestamps();

    $table->unique(['company_id', 'request_key']);

    $table->foreign(['company_id', 'account_receivable_id'], 'arp_company_receivable_fk')
        ->references(['company_id', 'id'])
        ->on('accounts_receivable');

    $table->foreign(['company_id', 'cash_session_id'], 'arp_company_cash_session_fk')
        ->references(['company_id', 'id'])
        ->on('cash_sessions');
});

    }

    public function down(): void
    {
        Schema::dropIfExists('account_receivable_payments');
        Schema::dropIfExists('accounts_receivable');
        Schema::dropIfExists('sale_payments');
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');
        Schema::table('companies', function (Blueprint $table): void {
            $table->dropColumn('last_sale_number');
        });
        Schema::table('cash_sessions', function (Blueprint $table): void {
            $table->unsignedInteger('opening_amount_cents')->change();
            $table->unsignedInteger('expected_closing_cents')->nullable()->change();
            $table->unsignedInteger('counted_closing_cents')->nullable()->change();
        });
        Schema::table('cash_movements', function (Blueprint $table): void {
            $table->unsignedInteger('amount_cents')->change();
        });
    }
};
