<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts_receivable', function (Blueprint $table): void {
            $table->string('description', 180)->nullable()->after('customer_id');
        });
    }

    public function down(): void
    {
        Schema::table('accounts_receivable', function (Blueprint $table): void {
            $table->dropColumn('description');
        });
    }
};
