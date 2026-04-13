<?php

namespace App\Modules\PILALiquidation\Commands;

use Illuminate\Console\Command;

/**
 * RF-120 — ETL desde AplicativoV6.accdb (Access histórico).
 *
 * Importa: historial de aportes pagados, cuentas de cobro, recibos de caja.
 * Preserva consecutivos y radicados existentes.
 *
 * REQUIERE: archivo .accdb exportado a CSV/JSON por el cliente.
 * Las 113 tablas del Access se mapean a las tablas Laravel según SKILL.md.
 *
 * @see DOCUMENTO_RECTOR §16, RF-120, SKILL.md §"Migración desde Access"
 */
final class EtlMigrateAccessCommand extends Command
{
    protected $signature = 'etl:migrate-access
        {path : Ruta al directorio con archivos CSV exportados del Access}
        {--dry-run : Simular sin escribir en BD}
        {--table=* : Tablas específicas a procesar}';

    protected $description = 'RF-120: Importa historial desde AplicativoV6.accdb (exportado a CSV)';

    /**
     * Mapeo de tablas Access → tablas Laravel.
     * Solo las tablas con datos relevantes para migración.
     */
    private const TABLE_MAP = [
        'TBL_AFILIADOS' => 'afl_affiliates',
        'TBL_PERSONAS' => 'afl_persons',
        'TBL_PAGADORES' => 'afl_payers',
        'TBL_APORTES' => 'pila_liquidations',
        'TBL_LINEAS_APORTE' => 'pila_liquidation_lines',
        'TBL_CUENTAS_COBRO' => 'bill_cuentas_cobro',
        'TBL_RECIBOS' => 'bill_invoices',
        'TBL_NOVEDADES' => 'afl_novelties',
        'TBL_CONSECUTIVOS' => 'radicado_yearly_sequences',
    ];

    public function handle(): int
    {
        $path = $this->argument('path');
        $dryRun = $this->option('dry-run');

        if (! is_dir($path)) {
            $this->error("Directorio no encontrado: {$path}");

            return self::FAILURE;
        }

        $this->info($dryRun ? '🔍 Modo DRY-RUN' : '▶ Iniciando ETL Access...');

        // TODO: Implementar lectura de CSVs cuando se reciba el export del Access
        $this->info('Estructura ETL Access lista. Pendiente: archivos CSV del cliente.');
        $this->table(
            ['Tabla Access', 'Tabla Laravel', 'Estado'],
            collect(self::TABLE_MAP)->map(fn ($laravel, $access) => [$access, $laravel, 'Pendiente datos'])->values()->all(),
        );

        return self::SUCCESS;
    }
}
