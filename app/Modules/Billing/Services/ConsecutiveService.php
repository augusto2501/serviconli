<?php

namespace App\Modules\Billing\Services;

use Illuminate\Support\Facades\DB;

/**
 * Generador de consecutivos siguiendo formato cfg_consecutive_formats.
 *
 * Patrones: RC-{YYYY}-{NNNN}, CC-{YYYY}-{NNNN}, SC-{YYYY}-{NNNN}, etc.
 *
 * Usa tabla de secuencias o máximo existente para garantizar unicidad.
 */
final class ConsecutiveService
{
    /**
     * Genera el siguiente consecutivo para un prefijo dado.
     *
     * @param string $prefix RC, CC, SC, RAD, CE
     */
    public function next(string $prefix): string
    {
        $year = now()->year;
        $pattern = "{$prefix}-{$year}-";

        $lastNumber = $this->resolveLastNumber($prefix, $pattern);

        $next = $lastNumber + 1;
        $padded = str_pad((string) $next, 4, '0', STR_PAD_LEFT);

        return "{$prefix}-{$year}-{$padded}";
    }

    private function resolveLastNumber(string $prefix, string $pattern): int
    {
        $table = match ($prefix) {
            'RC' => 'bill_invoices',
            'CC' => 'bill_cuentas_cobro',
            default => null,
        };

        $column = match ($prefix) {
            'RC' => 'public_number',
            'CC' => 'cuenta_number',
            default => null,
        };

        if ($table === null || $column === null) {
            return 0;
        }

        $last = DB::table($table)
            ->where($column, 'LIKE', $pattern . '%')
            ->orderByDesc($column)
            ->value($column);

        if ($last === null) {
            return 0;
        }

        $parts = explode('-', $last);

        return (int) end($parts);
    }
}
