<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DOCUMENTO_RECTOR §3.3 Grupo C — Contratos multi-ingreso independientes
// RF-030: IBC = 40% ingreso reportado, tope 25 SMMLV, D.1273/2018
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('afl_multi_income_contracts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('affiliate_id')->constrained('afl_affiliates')->cascadeOnDelete();
            $table->unsignedSmallInteger('period_year');
            $table->unsignedTinyInteger('period_month');
            $table->string('contract_description', 200)->nullable();
            // RF-030: ingreso reportado por este contrato — INT pesos (no DECIMAL)
            $table->unsignedInteger('income_pesos');
            // RF-030: IBC calculado = roundIBC(income * 40%) — ya aplicado el tope
            $table->unsignedInteger('ibc_contribution_pesos');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            // Nombre explícito corto (MySQL máx. 64 caracteres en identificadores)
            $table->index(['affiliate_id', 'period_year', 'period_month'], 'afl_mi_ct_aff_period_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('afl_multi_income_contracts');
    }
};
