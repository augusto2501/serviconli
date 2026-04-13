<?php

// RF-044 — referencia de cálculos legacy (Access) para comparación en transición

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pay_legacy_references', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('affiliate_id')->constrained('afl_affiliates')->cascadeOnDelete();
            $table->unsignedSmallInteger('period_year');
            $table->unsignedTinyInteger('period_month');
            $table->unsignedBigInteger('total_pesos');
            $table->json('subsystem_totals')->nullable();
            $table->string('source', 32)->default('ACCESS');
            $table->timestamps();

            $table->unique(['affiliate_id', 'period_year', 'period_month'], 'pay_legacy_ref_uniq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pay_legacy_references');
    }
};
