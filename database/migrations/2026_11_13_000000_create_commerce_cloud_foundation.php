<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('legal_name');
            $table->string('trade_name');
            $table->string('document', 32)->nullable()->unique();
            $table->string('email', 190)->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('timezone', 64)->default('America/Sao_Paulo');
            $table->string('status', 24)->default('active')->index();
            $table->timestamps();
        });

        Schema::create('company_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 32)->default('Administrador');
            $table->string('status', 24)->default('active');
            $table->timestamps();
            $table->unique(['company_id', 'user_id']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('company_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('setting_key', 100);
            $table->json('setting_value')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'setting_key']);
        });

        Schema::create('company_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('module_key', 64);
            $table->boolean('enabled')->default(false);
            $table->string('source', 24)->default('company');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'module_key']);
        });

        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name', 120);
            $table->string('billing_cycle', 24)->default('annual');
            $table->unsignedInteger('price_cents')->comment('Legacy/base value; use initial_price_cents and renewal_price_cents for subscription billing.');
            $table->unsignedInteger('initial_price_cents')->default(17900);
            $table->unsignedInteger('renewal_price_cents')->default(8000);
            $table->json('limits')->nullable();
            $table->json('features')->nullable();
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->string('provider', 32)->default('mercado_pago');
            $table->string('provider_reference', 120)->nullable();
            $table->string('status', 32)->default('pending')->index();
            $table->dateTime('current_period_start')->nullable();
            $table->dateTime('current_period_end')->nullable()->index();
            $table->dateTime('cancelled_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'provider_reference']);
            $table->index(['company_id', 'status']);
        });

        Schema::create('payment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 32)->default('mercado_pago');
            $table->string('provider_event_id', 120);
            $table->string('event_type', 64);
            $table->string('billing_stage', 24)->nullable();
            $table->unsignedInteger('expected_amount_cents')->nullable();
            $table->unsignedInteger('paid_amount_cents')->nullable();
            $table->string('status', 24)->default('received')->index();
            $table->dateTime('received_at');
            $table->dateTime('processed_at')->nullable();
            $table->string('panel_sync_status', 24)->default('not_required')->index();
            $table->dateTime('panel_synced_at')->nullable();
            $table->unsignedSmallInteger('panel_sync_attempts')->default(0);
            $table->text('panel_sync_error')->nullable();
            $table->json('payload')->nullable();
            $table->text('processing_error')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'provider_event_id']);
            $table->index(['subscription_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_events');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('plans');
        Schema::dropIfExists('company_modules');
        Schema::dropIfExists('company_settings');
        Schema::dropIfExists('company_memberships');
        Schema::dropIfExists('companies');
    }
};
