<?php

// DOCUMENTO_RECTOR §4 Grupo N — Cuadre de caja (3 líneas + cierre 13 conceptos)

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_daily_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->date('business_date');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 16)->default('ABIERTO');
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique('business_date');
        });

        Schema::create('cash_recon_affiliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reconciliation_id')->constrained('cash_daily_reconciliations')->cascadeOnDelete();
            $table->unsignedInteger('total_receipts')->default(0);
            $table->unsignedBigInteger('total_affiliation_value')->default(0);
            $table->unsignedBigInteger('total_advisor_commission')->default(0);
            $table->unsignedBigInteger('total_efectivo')->default(0);
            $table->unsignedBigInteger('total_consignacion')->default(0);
            $table->unsignedBigInteger('total_credito')->default(0);
            $table->unsignedBigInteger('total_cuenta_cobro')->default(0);
            $table->timestamps();

            $table->unique('reconciliation_id');
        });

        Schema::create('cash_recon_contributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reconciliation_id')->constrained('cash_daily_reconciliations')->cascadeOnDelete();
            $table->unsignedBigInteger('total_aporte_pos')->default(0);
            $table->unsignedBigInteger('total_admin')->default(0);
            $table->unsignedBigInteger('total_interest_mora')->default(0);
            $table->unsignedBigInteger('provision_mora')->default(0);
            $table->unsignedBigInteger('total_efectivo')->default(0);
            $table->unsignedBigInteger('total_consignacion')->default(0);
            $table->unsignedBigInteger('total_credito')->default(0);
            $table->unsignedBigInteger('total_cuenta_cobro')->default(0);
            $table->timestamps();

            $table->unique('reconciliation_id');
        });

        Schema::create('cash_recon_cuentas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reconciliation_id')->constrained('cash_daily_reconciliations')->cascadeOnDelete();
            $table->unsignedBigInteger('total_affiliations_cuentas')->default(0);
            $table->unsignedBigInteger('total_contributions_cuentas')->default(0);
            $table->unsignedBigInteger('total_admin_cuentas')->default(0);
            $table->unsignedBigInteger('total_efectivo')->default(0);
            $table->unsignedBigInteger('total_consignacion')->default(0);
            $table->unsignedBigInteger('total_credito')->default(0);
            $table->unsignedBigInteger('total_cuenta_cobro')->default(0);
            $table->timestamps();

            $table->unique('reconciliation_id');
        });

        // 13 conceptos consolidados — claves fijas en JSON (Documento Rector §8.2)
        Schema::create('cash_daily_close', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reconciliation_id')->constrained('cash_daily_reconciliations')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->json('concept_amounts');
            $table->unsignedBigInteger('grand_total_pesos')->default(0);
            $table->timestamps();

            $table->unique('reconciliation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_daily_close');
        Schema::dropIfExists('cash_recon_cuentas');
        Schema::dropIfExists('cash_recon_contributions');
        Schema::dropIfExists('cash_recon_affiliations');
        Schema::dropIfExists('cash_daily_reconciliations');
    }
};
