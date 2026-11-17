<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->unsignedInteger('last_work_order_number')->default(0);
        });

        Schema::create('service_work_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('work_order_number');
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('service_quote_id')->nullable();
            $table->unsignedBigInteger('service_appointment_id')->nullable();
            $table->string('asset_type', 40)->nullable();
            $table->string('asset_description', 180)->nullable();
            $table->string('asset_identifier', 80)->nullable();
            $table->string('status', 32)->default('open')->index();
            $table->text('complaint')->nullable();
            $table->text('diagnosis')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('warranty_days')->default(0);
            $table->dateTime('opened_at');
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('finalized_at')->nullable();
            $table->dateTime('stock_applied_at')->nullable();
            $table->dateTime('stock_reversed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'id']);
            $table->unique(['company_id', 'work_order_number']);
            $table->unique(['company_id', 'service_quote_id']);
            $table->unique(['company_id', 'service_appointment_id']);
            $table->foreign(['company_id', 'customer_id'])->references(['company_id', 'id'])->on('customers');
            $table->foreign(['company_id', 'service_quote_id'])->references(['company_id', 'id'])->on('service_quotes');
            $table->foreign(['company_id', 'service_appointment_id'])->references(['company_id', 'id'])->on('service_appointments');
            $table->index(['company_id', 'status', 'opened_at']);
        });

        Schema::create('service_work_order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('service_work_order_id');
            $table->string('item_type', 16);
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('name', 180);
            $table->text('description')->nullable();
            $table->decimal('quantity', 12, 3);
            $table->unsignedBigInteger('unit_price_cents');
            $table->unsignedBigInteger('total_cents');
            $table->timestamps();
            $table->foreign(['company_id', 'service_work_order_id'], 'swo_items_company_order_fk')->references(['company_id', 'id'])->on('service_work_orders')->cascadeOnDelete();
            $table->foreign(['company_id', 'product_id'])->references(['company_id', 'id'])->on('products');
            $table->index(['company_id', 'service_work_order_id']);
        });

        Schema::table('accounts_receivable', function (Blueprint $table): void {
            $table->unsignedBigInteger('service_work_order_id')->nullable();
            $table->unique(['company_id', 'service_work_order_id']);
            $table->foreign(['company_id', 'service_work_order_id'])->references(['company_id', 'id'])->on('service_work_orders');
        });
    }

    public function down(): void
    {
        Schema::table('accounts_receivable', function (Blueprint $table): void {
            $table->dropForeign(['company_id', 'service_work_order_id']);
            $table->dropUnique(['company_id', 'service_work_order_id']);
            $table->dropColumn('service_work_order_id');
        });
        Schema::dropIfExists('service_work_order_items');
        Schema::dropIfExists('service_work_orders');
        Schema::table('companies', function (Blueprint $table): void {
            $table->dropColumn('last_work_order_number');
        });
    }
};
