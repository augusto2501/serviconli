<?php

// RF-022 — filtro/export por operador PILA del pagador (RF-027)

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('afl_payers', function (Blueprint $table): void {
            $table->string('pila_operator_code', 32)->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('afl_payers', function (Blueprint $table): void {
            $table->dropColumn('pila_operator_code');
        });
    }
};
