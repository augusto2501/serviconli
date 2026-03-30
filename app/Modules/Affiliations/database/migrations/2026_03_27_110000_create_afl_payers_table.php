<?php

// DOCUMENTO_RECTOR §4 Grupo B — afl_payers (mínimo para FK de afl_affiliate_payer; columnas completas en fases posteriores)

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('afl_payers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->nullable()->constrained('core_people')->nullOnDelete();
            $table->string('nit', 32)->nullable();
            $table->unsignedTinyInteger('digito_verificacion')->nullable();
            $table->string('razon_social')->nullable();
            $table->string('status', 32)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('afl_payers');
    }
};
