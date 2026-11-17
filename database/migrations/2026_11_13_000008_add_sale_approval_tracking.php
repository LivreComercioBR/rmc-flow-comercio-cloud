<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table): void {
            $table->foreignId('cancelled_approved_by')->nullable()->after('cancelled_by')->constrained('users')->nullOnDelete();
        });
        Schema::table('sale_refunds', function (Blueprint $table): void {
            $table->foreignId('approved_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sale_refunds', function (Blueprint $table): void { $table->dropConstrainedForeignId('approved_by'); });
        Schema::table('sales', function (Blueprint $table): void { $table->dropConstrainedForeignId('cancelled_approved_by'); });
    }
};
