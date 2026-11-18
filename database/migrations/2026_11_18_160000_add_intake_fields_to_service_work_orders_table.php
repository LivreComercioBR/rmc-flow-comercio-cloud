<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_work_orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('service_work_orders', 'expected_at')) {
                $table->date('expected_at')->nullable();
            }
            if (! Schema::hasColumn('service_work_orders', 'mileage')) {
                $table->string('mileage', 80)->nullable();
            }
            if (! Schema::hasColumn('service_work_orders', 'fuel_level')) {
                $table->string('fuel_level', 80)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('service_work_orders', function (Blueprint $table): void {
            $columns = [];
            foreach (['expected_at', 'mileage', 'fuel_level'] as $column) {
                if (Schema::hasColumn('service_work_orders', $column)) {
                    $columns[] = $column;
                }
            }
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
