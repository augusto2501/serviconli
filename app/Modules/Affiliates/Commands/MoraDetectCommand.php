<?php

namespace App\Modules\Affiliates\Commands;

use App\Modules\Affiliates\Models\Affiliate;
use App\Modules\Affiliates\Services\AffiliateStatusMachine;
use App\Modules\Affiliates\Services\MoraPeriodTransitionService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * artisan mora:detect {--periodo=} {--apply}
 *
 * Detección diaria: afiliados obligados a cotizar sin línea PILA confirmada en el período.
 *
 * @see DOCUMENTO_RECTOR §5.4, RN-05
 */
class MoraDetectCommand extends Command
{
    protected $signature = 'mora:detect
        {--periodo= : YYYY-MM (por defecto mes calendario anterior)}
        {--apply : Aplicar la misma lógica que pila:transicion-periodo para ese período}';

    protected $description = 'Lista mora pendiente por período; opcionalmente aplica transición';

    public function handle(
        MoraPeriodTransitionService $transition,
        AffiliateStatusMachine $statusMachine,
    ): int {
        $opt = $this->option('periodo');
        if ($opt !== null && $opt !== '') {
            $parts = explode('-', (string) $opt);
            if (count($parts) !== 2) {
                $this->error('Use --periodo=YYYY-MM');

                return self::FAILURE;
            }
            $year = (int) $parts[0];
            $month = (int) $parts[1];
        } else {
            $prev = Carbon::now()->subMonth();
            $year = $prev->year;
            $month = $prev->month;
        }

        $missingIds = [];
        Affiliate::query()->with('status')->orderBy('id')->chunkById(200, function ($rows) use ($transition, $statusMachine, $year, $month, &$missingIds): void {
            foreach ($rows as $affiliate) {
                $code = $statusMachine->currentStatusCode($affiliate);
                if (in_array($code, ['RETIRADO', 'AFILIADO'], true)) {
                    continue;
                }
                if (! $transition->affiliateHasConfirmedPaymentForPeriod($affiliate->id, $year, $month)) {
                    $missingIds[] = $affiliate->id;
                }
            }
        });

        $this->info(sprintf('Período %04d-%02d — sin PILA confirmada (excl. AFILIADO/RETIRADO): %d', $year, $month, count($missingIds)));

        $beneficiaryAlert = 0;
        Affiliate::query()->with('status')->chunkById(200, function ($rows) use ($statusMachine, &$beneficiaryAlert): void {
            foreach ($rows as $affiliate) {
                if ($statusMachine->requiresBeneficiaryAlert($affiliate)) {
                    $beneficiaryAlert++;
                }
            }
        });
        $this->info('Afiliados con alerta beneficiarios (D.780 / mora > 1 mes): '.$beneficiaryAlert);

        if ($this->option('apply')) {
            $stats = $transition->runForPeriod($year, $month, false);
            $this->table(
                ['Tras --apply', ''],
                [
                    ['Escalados', (string) $stats['unpaid_escalated']],
                    ['Activados', (string) $stats['paid_activated']],
                    ['Omitidos', (string) $stats['skipped']],
                ],
            );
        }

        return self::SUCCESS;
    }
}
