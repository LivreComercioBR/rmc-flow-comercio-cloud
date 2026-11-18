<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->decimal('icms_rate', 10, 4)->default(0);
            $table->decimal('ipi_rate', 10, 4)->default(0);
            $table->decimal('import_tax_rate', 10, 4)->default(0);
            $table->decimal('export_tax_rate', 10, 4)->default(0);
            $table->decimal('iss_rate', 10, 4)->default(0);
            $table->decimal('operational_cost_rate', 10, 4)->default(0);
            $table->decimal('profit_rate', 10, 4)->default(0);
            $table->unsignedInteger('other_costs_cents')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn(['icms_rate', 'ipi_rate', 'import_tax_rate', 'export_tax_rate', 'iss_rate', 'operational_cost_rate', 'profit_rate', 'other_costs_cents']);
        });
    }
};
