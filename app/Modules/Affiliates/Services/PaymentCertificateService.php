<?php

namespace App\Modules\Affiliates\Services;

use App\Modules\Affiliates\Models\Affiliate;
use App\Modules\PILALiquidation\Enums\PilaLiquidationStatus;
use App\Modules\PILALiquidation\Models\PilaLiquidationLine;

/**
 * Certificado de pago por período — RN-22.
 *
 * @see DOCUMENTO_RECTOR — validación contra liquidación PILA confirmada
 */
final class PaymentCertificateService
{
    public function __construct(
        private readonly MoraPeriodTransitionService $moraPeriod,
    ) {}

    /**
     * @return array{
     *   period: array{year: int, month: int},
     *   paid: bool,
     *   line: array<string, mixed>|null,
     *   message: string,
     * }
     */
    public function forPeriod(Affiliate $affiliate, int $year, int $month): array
    {
        $paid = $this->moraPeriod->affiliateHasConfirmedPaymentForPeriod($affiliate->id, $year, $month);

        if (! $paid) {
            return [
                'period' => ['year' => $year, 'month' => $month],
                'paid' => false,
                'line' => null,
                'message' => 'No hay liquidación PILA confirmada para este período.',
            ];
        }

        $line = PilaLiquidationLine::query()
            ->join('pila_liquidations', 'pila_liquidations.id', '=', 'pila_liquidation_lines.pila_liquidation_id')
            ->where('pila_liquidations.affiliate_id', $affiliate->id)
            ->where('pila_liquidations.status', PilaLiquidationStatus::Confirmed->value)
            ->where('pila_liquidation_lines.period_year', $year)
            ->where('pila_liquidation_lines.period_month', $month)
            ->select('pila_liquidation_lines.*')
            ->orderByDesc('pila_liquidation_lines.id')
            ->first();

        return [
            'period' => ['year' => $year, 'month' => $month],
            'paid' => true,
            'line' => $line !== null ? [
                'ibcRoundedPesos' => $line->ibc_rounded_pesos,
                'totalSocialSecurityPesos' => $line->total_social_security_pesos,
                'subsystemAmountsPesos' => $line->subsystem_amounts_pesos,
                'daysLate' => $line->days_late,
            ] : null,
            'message' => 'Período con aporte registrado en PILA confirmada.',
        ];
    }
}
