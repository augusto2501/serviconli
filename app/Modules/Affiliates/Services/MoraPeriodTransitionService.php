<?php

namespace App\Modules\Affiliates\Services;

use App\Modules\Affiliates\Models\Affiliate;
use App\Modules\PILALiquidation\Enums\PilaLiquidationStatus;
use App\Modules\PILALiquidation\Listeners\UpdateMoraStatusOnPayment;
use Illuminate\Support\Facades\DB;

/**
 * Cierre mensual de mora por período — RN-05.
 *
 * Liquidación PILA confirmada con línea del período = pago registrado.
 * El listener {@see UpdateMoraStatusOnPayment}
 * ya desescala al guardar aportes; aquí solo se escala por falta de pago y se
 * activa AFILIADO si hubo pago sin pasar por ese flujo.
 *
 * @see DOCUMENTO_RECTOR §5.4
 */
final class MoraPeriodTransitionService
{
    public function __construct(
        private readonly AffiliateStatusMachine $statusMachine,
    ) {}

    public function affiliateHasConfirmedPaymentForPeriod(int $affiliateId, int $year, int $month): bool
    {
        return DB::table('pila_liquidation_lines as pll')
            ->join('pila_liquidations as pl', 'pl.id', '=', 'pll.pila_liquidation_id')
            ->where('pl.affiliate_id', $affiliateId)
            ->where('pl.status', PilaLiquidationStatus::Confirmed->value)
            ->where('pll.period_year', $year)
            ->where('pll.period_month', $month)
            ->exists();
    }

    /**
     * @return array{unpaid_escalated: int, paid_activated: int, skipped: int}
     */
    public function runForPeriod(int $year, int $month, bool $dryRun = false): array
    {
        $stats = [
            'unpaid_escalated' => 0,
            'paid_activated' => 0,
            'skipped' => 0,
        ];

        Affiliate::query()->with('status')->orderBy('id')->chunkById(100, function ($affiliates) use ($year, $month, $dryRun, &$stats): void {
            foreach ($affiliates as $affiliate) {
                $code = $this->statusMachine->currentStatusCode($affiliate);
                if ($code === 'RETIRADO') {
                    $stats['skipped']++;

                    continue;
                }

                $paid = $this->affiliateHasConfirmedPaymentForPeriod($affiliate->id, $year, $month);

                if ($paid) {
                    if ($code === 'AFILIADO') {
                        if (! $dryRun) {
                            $this->statusMachine->activateOnFirstPayment($affiliate);
                        }
                        $stats['paid_activated']++;
                    } else {
                        $stats['skipped']++;
                    }

                    continue;
                }

                if ($code === 'AFILIADO') {
                    $stats['skipped']++;

                    continue;
                }

                if (! $dryRun) {
                    $this->statusMachine->escalate($affiliate);
                }
                $stats['unpaid_escalated']++;
            }
        });

        return $stats;
    }
}
