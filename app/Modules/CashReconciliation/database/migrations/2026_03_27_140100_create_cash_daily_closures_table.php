<?php

// DOCUMENTO_RECTOR BC-07 — cierre de caja (tabla mínima; líneas de detalle en iteraciones)

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_daily_closures', function (Blueprint $table) {
            $table->id();
            $table->date('business_date');
            $table->unsignedBigInteger('opening_balance_pesos')->default(0);
            $table->unsignedBigInteger('closing_balance_pesos')->nullable();
            $table->string('status', 32)->default('OPEN');
            $table->timestamps();

            $table->unique('business_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_daily_closures');
    }
};
