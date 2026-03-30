<?php

// DOCUMENTO_RECTOR §4 Grupo D — pay_liquidation_batches (columnas núcleo; ampliar según BC-05)

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pay_liquidation_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payer_id')->nullable()->constrained('afl_payers')->nullOnDelete();
            $table->unsignedSmallInteger('period_year');
            $table->unsignedTinyInteger('period_month');
            $table->unsignedSmallInteger('cotization_year');
            $table->unsignedTinyInteger('cotization_month');
            $table->string('planilla_type', 8)->nullable();
            $table->string('status', 32)->default('BORRADOR');
            $table->unsignedBigInteger('total_health')->default(0);
            $table->unsignedBigInteger('total_pension')->default(0);
            $table->unsignedBigInteger('total_arl')->default(0);
            $table->unsignedBigInteger('total_ccf')->default(0);
            $table->unsignedBigInteger('total_solidarity')->default(0);
            $table->unsignedBigInteger('total_upc')->default(0);
            $table->unsignedBigInteger('total_admin')->default(0);
            $table->unsignedBigInteger('grand_total')->default(0);
            $table->unsignedInteger('cant_affiliates')->default(0);
            $table->unsignedBigInteger('operator_id')->nullable();
            $table->string('planilla_number', 64)->nullable();
            $table->date('payment_date')->nullable();
            $table->string('branch_code', 32)->nullable();
            $table->bigInteger('rounding_adjustment_total')->default(0);
            $table->unsignedBigInteger('valor_calculado_sistema')->nullable();
            $table->unsignedBigInteger('valor_pagado_operador')->nullable();
            $table->bigInteger('diferencia_reconciliacion')->nullable();
            $table->string('estado_reconciliacion', 32)->nullable();
            $table->string('generated_by', 191)->nullable();
            $table->timestamps();

            $table->index(['payer_id', 'period_year', 'period_month'], 'pay_liq_batch_payer_period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pay_liquidation_batches');
    }
};
