<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('opening_amount_cents');
            $table->unsignedInteger('expected_closing_cents')->nullable();
            $table->unsignedInteger('counted_closing_cents')->nullable();
            $table->bigInteger('difference_cents')->nullable();
            $table->string('status', 16)->default('open')->index();
            $table->dateTime('opened_at');
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('closing_note', 180)->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'id']);
            $table->index(['company_id', 'status', 'opened_at']);
        });

        Schema::create('cash_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('cash_session_id');
            $table->string('direction', 8);
            $table->string('movement_type', 32);
            $table->unsignedInteger('amount_cents');
            $table->string('description', 180);
            $table->string('source_type', 40)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('request_key', 36)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'request_key']);
            $table->unique(['company_id', 'source_type', 'source_id']);
            $table->index(['company_id', 'cash_session_id', 'created_at']);
            $table->foreign(['company_id', 'cash_session_id'])->references(['company_id', 'id'])->on('cash_sessions');
        });

        Schema::table('account_payable_payments', function (Blueprint $table): void {
            $table->unsignedBigInteger('cash_session_id')->nullable()->after('payment_method');
            $table->index(['company_id', 'cash_session_id']);
            $table->foreign(['company_id', 'cash_session_id'])->references(['company_id', 'id'])->on('cash_sessions');
        });
    }

    public function down(): void
    {
        Schema::table('account_payable_payments', function (Blueprint $table): void {
            $table->dropForeign(['company_id', 'cash_session_id']);
            $table->dropIndex(['company_id', 'cash_session_id']);
            $table->dropColumn('cash_session_id');
        });
        Schema::dropIfExists('cash_movements');
        Schema::dropIfExists('cash_sessions');
    }
};
