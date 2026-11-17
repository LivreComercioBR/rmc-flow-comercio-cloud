<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_events', function (Blueprint $table): void {
            // 160 chars keeps this composite index below MySQL 5.7's legacy 767-byte key limit with utf8mb4.
            $table->string('purchase_reference', 160)->nullable()->after('provider_event_id');
            $table->index(['subscription_id', 'purchase_reference'], 'payment_events_subscription_purchase_index');
        });
    }

    public function down(): void
    {
        Schema::table('payment_events', function (Blueprint $table): void {
            $table->dropIndex('payment_events_subscription_purchase_index');
            $table->dropColumn('purchase_reference');
        });
    }
};
