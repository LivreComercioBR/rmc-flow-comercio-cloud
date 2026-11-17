<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_assets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('customer_id');
            $table->string('asset_type', 40);
            $table->string('name', 180);
            $table->string('manufacturer', 120)->nullable();
            $table->string('model', 120)->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('color', 60)->nullable();
            $table->string('identifier', 80)->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 16)->default('active')->index();
            $table->timestamps();
            $table->unique(['company_id', 'id']);
            $table->foreign(['company_id', 'customer_id'])->references(['company_id', 'id'])->on('customers');
            $table->index(['company_id', 'customer_id', 'status', 'name']);
            $table->index(['company_id', 'identifier']);
        });

        Schema::table('service_work_orders', function (Blueprint $table): void {
            $table->unsignedBigInteger('service_asset_id')->nullable();
            $table->string('asset_manufacturer', 120)->nullable();
            $table->string('asset_model', 120)->nullable();
            $table->unsignedSmallInteger('asset_year')->nullable();
            $table->string('asset_color', 60)->nullable();
            $table->foreign(['company_id', 'service_asset_id'])->references(['company_id', 'id'])->on('service_assets');
            $table->index(['company_id', 'service_asset_id']);
        });
    }

    public function down(): void
    {
        Schema::table('service_work_orders', function (Blueprint $table): void {
            $table->dropForeign(['company_id', 'service_asset_id']);
            $table->dropIndex(['company_id', 'service_asset_id']);
            $table->dropColumn(['service_asset_id', 'asset_manufacturer', 'asset_model', 'asset_year', 'asset_color']);
        });
        Schema::dropIfExists('service_assets');
    }
};
