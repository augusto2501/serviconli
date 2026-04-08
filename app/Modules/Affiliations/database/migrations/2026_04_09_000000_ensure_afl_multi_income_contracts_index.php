<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reparación deploy: si la migración anterior falló tras crear la tabla pero sin índice,
 * añade el índice con nombre corto. Idempotente; usa Schema::hasIndex (MySQL + SQLite).
 */
return new class extends Migration
{
    private const INDEX = 'afl_mi_ct_aff_period_idx';

    public function up(): void
    {
        if (! Schema::hasTable('afl_multi_income_contracts')) {
            return;
        }

        if (Schema::hasIndex('afl_multi_income_contracts', self::INDEX)) {
            return;
        }

        Schema::table('afl_multi_income_contracts', function (Blueprint $table): void {
            $table->index(['affiliate_id', 'period_year', 'period_month'], self::INDEX);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('afl_multi_income_contracts')) {
            return;
        }

        if (! Schema::hasIndex('afl_multi_income_contracts', self::INDEX)) {
            return;
        }

        Schema::table('afl_multi_income_contracts', function (Blueprint $table): void {
            $table->dropIndex(self::INDEX);
        });
    }
};
