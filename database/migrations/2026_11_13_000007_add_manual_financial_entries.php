<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_payables', function (Blueprint $table): void {
            $table->unsignedBigInteger('financial_category_id')->nullable();
            $table->string('manual_request_key', 50)->nullable();
            $table->unique(['company_id', 'manual_request_key']);
            $table->foreign(['company_id', 'financial_category_id'])->references(['company_id', 'id'])->on('categories');
        });

        Schema::table('accounts_receivable', function (Blueprint $table): void {
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('financial_category_id')->nullable();
            $table->string('manual_request_key', 50)->nullable();
            $table->unique(['company_id', 'manual_request_key']);
            $table->foreign(['company_id', 'customer_id'])->references(['company_id', 'id'])->on('customers');
            $table->foreign(['company_id', 'financial_category_id'])->references(['company_id', 'id'])->on('categories');
            $table->dropForeign(['company_id', 'sale_id']);
            $table->unsignedBigInteger('sale_id')->nullable()->change();
            $table->foreign(['company_id', 'sale_id'])->references(['company_id', 'id'])->on('sales');
        });
    }

    public function down(): void
    {
        Schema::table('accounts_receivable', function (Blueprint $table): void {
            $table->dropForeign(['company_id', 'sale_id']);
            $table->unsignedBigInteger('sale_id')->nullable(false)->change();
            $table->foreign(['company_id', 'sale_id'])->references(['company_id', 'id'])->on('sales')->cascadeOnDelete();
            $table->dropForeign(['company_id', 'customer_id']);
            $table->dropForeign(['company_id', 'financial_category_id']);
            $table->dropUnique(['company_id', 'manual_request_key']);
            $table->dropColumn(['customer_id', 'financial_category_id', 'manual_request_key']);
        });
        Schema::table('account_payables', function (Blueprint $table): void {
            $table->dropForeign(['company_id', 'financial_category_id']);
            $table->dropUnique(['company_id', 'manual_request_key']);
            $table->dropColumn(['financial_category_id', 'manual_request_key']);
        });
    }
};
