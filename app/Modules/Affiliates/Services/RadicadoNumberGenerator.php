<?php

namespace App\Modules\Affiliates\Services;

// RF-008 — formato RAD-{YYYY}-{NNNNNN} con lock de concurrencia

use Illuminate\Support\Facades\DB;

final class RadicadoNumberGenerator
{
    /** Genera el siguiente radicado para el año calendario actual (transacción + bloqueo de fila). */
    public function next(): string
    {
        return DB::transaction(function (): string {
            $year = (int) date('Y');

            DB::table('radicado_yearly_sequences')->insertOrIgnore([
                'year' => $year,
                'last_sequence' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $row = DB::table('radicado_yearly_sequences')
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                throw new \RuntimeException('No se pudo inicializar la secuencia de radicados.');
            }

            $next = ((int) $row->last_sequence) + 1;

            DB::table('radicado_yearly_sequences')
                ->where('year', $year)
                ->update([
                    'last_sequence' => $next,
                    'updated_at' => now(),
                ]);

            return sprintf('RAD-%d-%06d', $year, $next);
        });
    }
}
