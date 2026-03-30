<?php

// DOCUMENTO_RECTOR §4 Grupo D — pay_liquidation_entity_summary (totales por entidad del lote)

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pay_liquidation_entity_summary', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('pay_liquidation_batches')->cascadeOnDelete();
            $table->string('entity_pila_code', 32);
            $table->string('subsystem', 32)->nullable();
            $table->unsignedBigInteger('amount_pesos')->default(0);
            $table->timestamps();

            $table->index(['batch_id', 'entity_pila_code'], 'pay_liq_ent_batch_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pay_liquidation_entity_summary');
    }
};
