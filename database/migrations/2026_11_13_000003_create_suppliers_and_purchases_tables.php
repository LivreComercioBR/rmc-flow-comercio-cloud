<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('document', 32)->nullable();
            $table->string('contact_name', 120)->nullable();
            $table->string('email', 190)->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('address', 255)->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 24)->default('active')->index();
            $table->timestamps();
            $table->unique(['company_id', 'document']);
            $table->unique(['company_id', 'id']);
            $table->index(['company_id', 'name']);
            $table->index(['company_id', 'phone']);
        });

        Schema::create('purchases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('supplier_id');
            $table->string('invoice_number', 64)->nullable();
            $table->date('purchased_at');
            $table->unsignedInteger('total_cents');
            $table->unsignedTinyInteger('installment_count')->default(1);
            $table->string('status', 24)->default('completed')->index();
            $table->string('request_key', 36);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancellation_reason', 180)->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'id']);
            $table->unique(['company_id', 'request_key']);
            $table->unique(['company_id', 'supplier_id', 'invoice_number']);
            $table->index(['company_id', 'purchased_at', 'status']);
            $table->foreign(['company_id', 'supplier_id'])->references(['company_id', 'id'])->on('suppliers');
        });

        Schema::create('purchase_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('purchase_id');
            $table->unsignedBigInteger('product_id');
            $table->decimal('quantity', 12, 3);
            $table->unsignedInteger('unit_cost_cents');
            $table->unsignedInteger('previous_cost_price_cents')->default(0);
            $table->unsignedInteger('total_cents');
            $table->timestamps();
            $table->unique(['company_id', 'purchase_id', 'product_id']);
            $table->index(['company_id', 'product_id']);
            $table->foreign(['company_id', 'purchase_id'])->references(['company_id', 'id'])->on('purchases')->cascadeOnDelete();
            $table->foreign(['company_id', 'product_id'])->references(['company_id', 'id'])->on('products');
        });

        Schema::create('account_payables', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('purchase_id')->nullable();
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->string('description', 180);
            $table->unsignedTinyInteger('installment_number')->default(1);
            $table->date('due_date');
            $table->unsignedInteger('amount_cents');
            $table->unsignedInteger('paid_cents')->default(0);
            $table->unsignedInteger('cancelled_balance_cents')->default(0);
            $table->string('status', 24)->default('pending')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'id']);
            $table->unique(['company_id', 'purchase_id', 'installment_number'], 'ap_company_purchase_installment_uq');
            $table->index(['company_id', 'status', 'due_date']);
            $table->foreign(['company_id', 'purchase_id'])->references(['company_id', 'id'])->on('purchases');
            $table->foreign(['company_id', 'supplier_id'])->references(['company_id', 'id'])->on('suppliers');
        });

        Schema::create('account_payable_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('account_payable_id');
            $table->unsignedInteger('amount_cents');
            $table->dateTime('paid_at');
            $table->string('payment_method', 32);
            $table->string('request_key', 36);
            $table->string('reference', 100)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'request_key']);
            $table->index(['company_id', 'account_payable_id', 'paid_at'], 'app_company_payable_paid_idx');
            $table->foreign(['company_id', 'account_payable_id'])->references(['company_id', 'id'])->on('account_payables');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_payable_payments');
        Schema::dropIfExists('account_payables');
        Schema::dropIfExists('purchase_items');
        Schema::dropIfExists('purchases');
        Schema::dropIfExists('suppliers');
    }
};
