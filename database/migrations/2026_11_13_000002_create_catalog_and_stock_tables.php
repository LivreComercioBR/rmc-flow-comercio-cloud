<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('kind', 24)->default('product');
            $table->string('status', 24)->default('active')->index();
            $table->timestamps();
            $table->unique(['company_id', 'name', 'kind']);
            $table->unique(['company_id', 'id']);
        });

        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->string('name', 180);
            $table->string('sku', 64)->nullable();
            $table->string('barcode', 64)->nullable();
            $table->string('unit', 12)->default('un');
            $table->unsignedInteger('cost_price_cents')->default(0);
            $table->unsignedInteger('sale_price_cents')->default(0);
            $table->decimal('stock_quantity', 12, 3)->default(0);
            $table->decimal('minimum_stock', 12, 3)->default(0);
            $table->string('shelf', 80)->nullable();
            $table->string('status', 24)->default('active')->index();
            $table->timestamps();
            $table->unique(['company_id', 'sku']);
            $table->unique(['company_id', 'barcode']);
            $table->unique(['company_id', 'id']);
            $table->index(['company_id', 'name']);
            $table->index(['company_id', 'category_id', 'status']);
            $table->foreign(['company_id', 'category_id'])->references(['company_id', 'id'])->on('categories');
        });

        Schema::create('stock_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('product_id');
            $table->string('movement_type', 24);
            $table->decimal('quantity_delta', 12, 3);
            $table->decimal('balance_after', 12, 3);
            $table->string('reason', 180);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['company_id', 'product_id', 'created_at']);
            $table->foreign(['company_id', 'product_id'])->references(['company_id', 'id'])->on('products')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
    }
};
