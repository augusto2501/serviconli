<?php

namespace App\Modules\Affiliates\Commands;

use App\Modules\Affiliates\Services\MoraPeriodTransitionService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * artisan pila:transicion-periodo {periodo?} {--dry-run}
 *
 * @see DOCUMENTO_RECTOR §5.4, RN-05 — transición tras cierre de período
 */
class TransicionPeriodoCommand extends Command
{
    protected $signature = 'pila:transicion-periodo
        {periodo? : Período YYYY-MM (por defecto: mes calendario anterior)}
        {--dry-run : Mostrar resultado sin guardar cambios}';

    protected $description = 'Escala mora por falta de PILA confirmada en el período; activa AFILIADO si hay pago';

    public function handle(MoraPeriodTransitionService $service): int
    {
        $arg = $this->argument('periodo');
        if ($arg !== null && $arg !== '') {
            $parts = explode('-', (string) $arg);
            if (count($parts) !== 2) {
                $this->error('Formato inválido. Use YYYY-MM (ej: 2026-02).');

                return self::FAILURE;
            }
            $year = (int) $parts[0];
            $month = (int) $parts[1];
        } else {
            $prev = Carbon::now()->subMonth();
            $year = $prev->year;
            $month = $prev->month;
        }

        if ($year < 2000 || $month < 1 || $month > 12) {
            $this->error('Período numérico inválido.');

            return self::FAILURE;
        }

        $dry = (bool) $this->option('dry-run');
        $stats = $service->runForPeriod($year, $month, $dry);

        $this->info(sprintf('Período %04d-%02d%s', $year, $month, $dry ? ' (dry-run)' : ''));
        $this->table(
            ['Métrica', 'Cantidad'],
            [
                ['Escalados (sin pago PILA confirmado)', (string) $stats['unpaid_escalated']],
                ['Activados (AFILIADO con pago en período)', (string) $stats['paid_activated']],
                ['Omitidos', (string) $stats['skipped']],
            ],
        );

        $this->warn('Ejecutar una sola vez por período cerrado: repetir puede volver a escalar mora.');

        return self::SUCCESS;
    }
}
