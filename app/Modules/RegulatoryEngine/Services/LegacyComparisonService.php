<?php

namespace App\Modules\RegulatoryEngine\Services;

/**
 * RF-044 — Compara cálculo PILA vs referencia legacy (Access).
 * Si la diferencia es > 1%, genera alerta de transición.
 *
 * Usado durante período de transición Access→Laravel para garantizar
 * paridad numérica. La referencia legacy puede venir de:
 *   - pay_legacy_references (tabla ETL con cálculos históricos del Access)
 *   - Valor manual enviado por el operador
 *
 * @see DOCUMENTO_RECTOR §3.1, RF-044
 */
final class LegacyComparisonService
{
    private const TOLERANCE_PERCENT = 1.0;

    /**
     * Compara el total calculado vs referencia legacy.
     * Retorna null si está dentro de tolerancia, o un array con el detalle.
     *
     * @return array{difference_pesos: int, difference_percent: float, message: string}|null
     */
    public function compare(int $calculatedTotalPesos, ?int $legacyTotalPesos): ?array
    {
        if ($legacyTotalPesos === null || $legacyTotalPesos === 0) {
            return null;
        }

        $diff = abs($calculatedTotalPesos - $legacyTotalPesos);
        $percent = ($diff / $legacyTotalPesos) * 100;

        if ($percent <= self::TOLERANCE_PERCENT) {
            return null;
        }

        return [
            'difference_pesos' => $calculatedTotalPesos - $legacyTotalPesos,
            'difference_percent' => round($percent, 2),
            'message' => sprintf(
                'ALERTA TRANSICIÓN: diferencia del %.2f%% entre cálculo PILA ($%s) y referencia legacy ($%s). '
                .'Verifique antes de confirmar. [RF-044]',
                $percent,
                number_format($calculatedTotalPesos, 0, ',', '.'),
                number_format($legacyTotalPesos, 0, ',', '.'),
            ),
        ];
    }

    /**
     * Busca referencia legacy en BD para el afiliado y período.
     * Retorna null si no hay referencia (no bloquea el flujo).
     */
    public function findLegacyReference(int $affiliateId, int $periodYear, int $periodMonth): ?int
    {
        $ref = \Illuminate\Support\Facades\DB::table('pay_legacy_references')
            ->where('affiliate_id', $affiliateId)
            ->where('period_year', $periodYear)
            ->where('period_month', $periodMonth)
            ->value('total_pesos');

        return $ref !== null ? (int) $ref : null;
    }
}
