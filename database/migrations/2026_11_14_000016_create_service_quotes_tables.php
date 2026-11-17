<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->unsignedInteger('last_quote_number')->default(0);
        });

        Schema::create('service_quotes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quote_number');
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('service_appointment_id')->nullable();
            $table->unsignedBigInteger('subtotal_cents');
            $table->text('notes')->nullable();
            $table->date('expires_at');
            $table->string('status', 16)->default('draft')->index();
            $table->dateTime('sent_at')->nullable();
            $table->dateTime('decision_at')->nullable();
            $table->foreignId('decision_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('decision_contact_name', 160)->nullable();
            $table->string('decision_channel', 20)->nullable();
            $table->text('decision_notes')->nullable();
            $table->string('request_key', 36);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'id']);
            $table->unique(['company_id', 'quote_number']);
            $table->unique(['company_id', 'request_key']);
            $table->foreign(['company_id', 'customer_id'])->references(['company_id', 'id'])->on('customers');
            $table->foreign(['company_id', 'service_appointment_id'])->references(['company_id', 'id'])->on('service_appointments');
            $table->index(['company_id', 'status', 'expires_at']);
            $table->index(['company_id', 'customer_id', 'created_at']);
        });

        Schema::create('service_quote_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('service_quote_id');
            $table->string('item_type', 16);
            $table->unsignedBigInteger('service_catalog_item_id')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('name', 180);
            $table->text('description')->nullable();
            $table->decimal('quantity', 12, 3);
            $table->unsignedBigInteger('unit_price_cents');
            $table->unsignedBigInteger('total_cents');
            $table->timestamps();
            $table->foreign(['company_id', 'service_quote_id'])->references(['company_id', 'id'])->on('service_quotes')->cascadeOnDelete();
            $table->foreign(['company_id', 'service_catalog_item_id'])->references(['company_id', 'id'])->on('service_catalog_items');
            $table->foreign(['company_id', 'product_id'])->references(['company_id', 'id'])->on('products');
            $table->index(['company_id', 'service_quote_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_quote_items');
        Schema::dropIfExists('service_quotes');
        Schema::table('companies', function (Blueprint $table): void {
            $table->dropColumn('last_quote_number');
        });
    }
};
