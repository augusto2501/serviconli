<?php

namespace App\Modules\PILALiquidation\Commands;

use App\Modules\PILALiquidation\Models\LiquidationBatch;
use App\Modules\PILALiquidation\Services\PILAFileGenerationService;
use Illuminate\Console\Command;

/**
 * artisan pila:generar-planilla {periodo} {--empleador=} {--todos} {--formato=PLANO_ARUS}
 *
 * @see DOCUMENTO_RECTOR §6 Flujo 8, RN-21
 */
class GenerarPlanillaCommand extends Command
{
    protected $signature = 'pila:generar-planilla
        {periodo : Período en formato YYYY-MM}
        {--empleador= : NIT del pagador/empleador}
        {--todos : Generar para todos los lotes del período}
        {--formato=PLANO_ARUS : Formato: PLANO_ARUS o XLSX}';

    protected $description = 'Genera archivo PILA (plano ARUS o XLSX) desde lotes confirmados';

    public function handle(PILAFileGenerationService $service): int
    {
        $periodo = $this->argument('periodo');
        $parts = explode('-', $periodo);
        if (count($parts) !== 2) {
            $this->error('Formato de período inválido. Use YYYY-MM (ej: 2026-03).');

            return self::FAILURE;
        }

        $year = (int) $parts[0];
        $month = (int) $parts[1];
        $formato = $this->option('formato');
        $nit = $this->option('empleador');
        $todos = $this->option('todos');

        $query = LiquidationBatch::query()
            ->where('period_year', $year)
            ->where('period_month', $month)
            ->where('status', 'LIQUIDADO');

        if ($nit && ! $todos) {
            $query->whereHas('payer', fn ($q) => $q->where('nit', $nit));
        }

        $batches = $query->get();

        if ($batches->isEmpty()) {
            $this->warn("No se encontraron lotes confirmados para {$periodo}.");

            return self::SUCCESS;
        }

        $this->info("Encontrados {$batches->count()} lote(s) para {$periodo}.");

        $generated = 0;
        foreach ($batches as $batch) {
            $payerLabel = $batch->payer?->razon_social ?? "Lote #{$batch->id}";
            $this->line("  Generando: {$payerLabel}...");

            try {
                $gen = $service->generate($batch->id, $formato, 'artisan');
                $this->info("    ✓ Archivo: {$gen->file_path} ({$gen->affiliates_count} afiliados)");
                $generated++;
            } catch (\Throwable $e) {
                $this->error("    ✗ Error: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info("Generados: {$generated}/{$batches->count()} archivo(s).");

        return self::SUCCESS;
    }
}
