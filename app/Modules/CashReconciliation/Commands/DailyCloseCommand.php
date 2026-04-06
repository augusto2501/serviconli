<?php

namespace App\Modules\CashReconciliation\Commands;

use App\Modules\CashReconciliation\Services\DailyCloseService;
use App\Modules\CashReconciliation\Services\DailyReconciliationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Cierre fin de día — Flujo 10, DOCUMENTO_RECTOR §8.2.
 */
class DailyCloseCommand extends Command
{
    protected $signature = 'daily:close
                            {date? : Fecha negocio YYYY-MM-DD (por defecto ayer)}
                            {--force : Cerrar aunque ya exista cierre (no implementado)}';

    protected $description = 'Calcula cuadre de caja (3 líneas) y registra cierre con 13 conceptos';

    public function __construct(
        private readonly DailyReconciliationService $reconciliationService,
        private readonly DailyCloseService $closeService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $dateStr = $this->argument('date');
        $carbon = $dateStr
            ? Carbon::parse($dateStr)->startOfDay()
            : Carbon::yesterday();

        $r = $this->reconciliationService->getOrCreateForDate($carbon);

        if ($r->isClosed()) {
            $this->error('El cuadre del día '.$carbon->toDateString().' ya está CERRADO.');

            return self::FAILURE;
        }

        $this->closeService->close($r, null);
        $r->refresh()->load(['affiliationsLine', 'contributionsLine', 'cuentasLine', 'dailyClose']);

        $this->info('Cierre registrado para '.$carbon->toDateString().'.');
        $this->line('Total día: $'.number_format($r->dailyClose?->grand_total_pesos ?? 0, 0, ',', '.'));

        return self::SUCCESS;
    }
}
