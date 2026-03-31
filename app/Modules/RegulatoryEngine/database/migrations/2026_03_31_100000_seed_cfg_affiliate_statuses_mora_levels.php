<?php

/**
 * RF-071: Máquina de estados de mora escalonada.
 * Completa los estados faltantes: ACTIVO, SUSPENDIDO, MORA_30..120_PLUS, PAGO_MES_SUBSIGUIENTE.
 *
 * Orden: AFILIADO(10) → ACTIVO(20) → SUSPENDIDO(30) → MORA_30(40) → MORA_60(50)
 *        → MORA_90(60) → MORA_120(70) → MORA_120_PLUS(75) → RETIRADO(80)
 *
 * @see DOCUMENTO_RECTOR §5.4, RF-071..RF-074
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $rows = [
            ['code' => 'ACTIVO', 'name' => 'Activo', 'sort_order' => 20],
            ['code' => 'SUSPENDIDO', 'name' => 'Suspendido', 'sort_order' => 30],
            ['code' => 'PAGO_MES_SUBSIGUIENTE', 'name' => 'Pago mes subsiguiente', 'sort_order' => 15],
            ['code' => 'MORA_30', 'name' => 'Mora 30 días', 'sort_order' => 40],
            ['code' => 'MORA_60', 'name' => 'Mora 60 días', 'sort_order' => 50],
            ['code' => 'MORA_90', 'name' => 'Mora 90 días', 'sort_order' => 60],
            ['code' => 'MORA_120', 'name' => 'Mora 120 días', 'sort_order' => 65],
            ['code' => 'MORA_120_PLUS', 'name' => 'Mora +120 días', 'sort_order' => 68],
        ];

        foreach ($rows as $row) {
            DB::table('cfg_affiliate_statuses')->insertOrIgnore([
                'code' => $row['code'],
                'name' => $row['name'],
                'sort_order' => $row['sort_order'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::table('cfg_affiliate_statuses')
            ->where('code', 'INACTIVO')
            ->update(['sort_order' => 72]);
    }

    public function down(): void
    {
        DB::table('cfg_affiliate_statuses')
            ->whereIn('code', [
                'ACTIVO', 'SUSPENDIDO', 'PAGO_MES_SUBSIGUIENTE',
                'MORA_30', 'MORA_60', 'MORA_90', 'MORA_120', 'MORA_120_PLUS',
            ])
            ->delete();
    }
};
