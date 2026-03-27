<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pila_liquidation_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pila_liquidation_id')->constrained('pila_liquidations')->cascadeOnDelete();
            $table->unsignedSmallInteger('line_number');
            $table->unsignedSmallInteger('period_year');
            $table->unsignedTinyInteger('period_month');
            $table->unsignedBigInteger('raw_ibc_pesos');
            $table->unsignedBigInteger('ibc_rounded_pesos');
            $table->unsignedInteger('days_late');
            $table->date('payment_deadline_date');
            $table->json('subsystem_amounts_pesos');
            $table->unsignedBigInteger('total_social_security_pesos');
            $table->timestamps();

            $table->unique(['pila_liquidation_id', 'period_year', 'period_month'], 'pila_liq_line_period_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pila_liquidation_lines');
    }
};
