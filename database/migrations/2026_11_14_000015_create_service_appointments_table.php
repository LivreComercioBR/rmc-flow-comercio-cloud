<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_appointments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('service_catalog_item_id');
            $table->string('service_name', 160);
            $table->unsignedBigInteger('service_price_cents');
            $table->dateTime('scheduled_at');
            $table->text('notes')->nullable();
            $table->string('status', 16)->default('scheduled')->index();
            $table->string('request_key', 36);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'id']);
            $table->unique(['company_id', 'request_key']);
            $table->foreign(['company_id', 'customer_id'])->references(['company_id', 'id'])->on('customers');
            $table->foreign(['company_id', 'service_catalog_item_id'])->references(['company_id', 'id'])->on('service_catalog_items');
            $table->index(['company_id', 'scheduled_at', 'status']);
            $table->index(['company_id', 'customer_id', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_appointments');
    }
};
