<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_catalog_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('price_cents')->default(0);
            $table->string('status', 24)->default('active')->index();
            $table->timestamps();
            $table->unique(['company_id', 'name']);
            $table->unique(['company_id', 'id']);
            $table->index(['company_id', 'status', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_catalog_items');
    }
};
