<?php

/**
 * afl_novelties — Novedades PILA por afiliado y período.
 *
 * 18+ tipos: ING, RET, LMA, LPA, IGE, IRL, SLN, LLU, TAE, TAP, TDE, TDP,
 * VSP, VST, VTE, AVP, VCT, COR.
 *
 * @see DOCUMENTO_RECTOR §3.4, §4 Grupo B, RF-061..RF-066
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('afl_novelties', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('affiliate_id')->constrained('afl_affiliates')->cascadeOnDelete();
            $table->foreignId('payer_id')->nullable()->constrained('afl_payers')->nullOnDelete();
            $table->smallInteger('period_year');
            $table->tinyInteger('period_month');
            $table->string('novelty_type_code', 3)->index();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();

            // Para traslados (TAE/TAP)
            $table->unsignedBigInteger('previous_entity_id')->nullable();
            $table->unsignedBigInteger('new_entity_id')->nullable();

            // Para variaciones (VSP/VST)
            $table->bigInteger('previous_value')->nullable();
            $table->bigInteger('new_value')->nullable();

            // Para retiros (RET)
            $table->string('retirement_scope', 20)->nullable()
                ->comment('TOTAL (X), PENSION_ONLY (P), ARL_ONLY (R)');
            $table->string('retirement_cause', 50)->nullable()
                ->comment('VOLUNTARIO, MORA_EN_APORTE, RETIRO_ESPECIAL');

            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['affiliate_id', 'period_year', 'period_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('afl_novelties');
    }
};
