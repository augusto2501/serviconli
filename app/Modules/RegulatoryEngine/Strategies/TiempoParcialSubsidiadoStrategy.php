<?php

namespace App\Modules\RegulatoryEngine\Strategies;

use App\Modules\RegulatoryEngine\DTOs\CalculationContext;
use App\Modules\RegulatoryEngine\Enums\SubsystemType;
use App\Modules\RegulatoryEngine\Services\RoundingEngine;
use App\Modules\RegulatoryEngine\ValueObjects\IBC;
use App\Modules\RegulatoryEngine\ValueObjects\MontoAporte;

/**
 * Tiempo parcial subsidiado — tipo 51.
 *
 * Particularidad RN-02: Pensión se calcula por semanas.
 *   Pensión = (Salario÷4) × número_semanas × TarifaAFP
 *   Días válidos: 7 (1 sem), 14 (2 sem), 21 (3 sem), 30 (4 sem).
 *
 * Subsistemas: S + P + ARL.
 * Pago VENCIDO.
 *
 * @see DOCUMENTO_RECTOR §3.2, RN-02, RF-033, RF-058
 */
final class TiempoParcialSubsidiadoStrategy implements ContributorCalculationStrategy
{
    private const VALID_DAYS = [7, 14, 21, 30];

    public function supportedCodes(): array
    {
        return ['51'];
    }

    public function applicableSubsystems(): array
    {
        return [SubsystemType::SALUD, SubsystemType::PENSION, SubsystemType::ARL];
    }

    public function calculate(CalculationContext $context): array
    {
        $ibcSalud = $context->ibcSalud();

        // RN-02: Pensión por semanas — Portado de Form_005 línea 11287
        $weeks = $this->daysToWeeks($context->daysAfp);
        $pensionBase = intval(($context->salaryPesos / 4) * $weeks);
        $ibcPension = new IBC(RoundingEngine::roundIBC($pensionBase));

        $health = MontoAporte::calcular($ibcSalud, $context->healthRatePercent);
        $pension = MontoAporte::calcular($ibcPension, $context->pensionRatePercent);
        $arl = MontoAporte::calcularARL($ibcSalud, $context->arlRatePercent);

        $adminFee = 0;
        if ($context->adminFeePesos > 0) {
            $adminFee = RoundingEngine::roundLegacy(
                (int) round(($context->adminFeePesos / 30) * $context->daysEps, 0)
            );
        }

        return [
            'ibc_salud_pesos' => $ibcSalud->valueInPesos,
            'ibc_pension_pesos' => $ibcPension->valueInPesos,
            'health_total_pesos' => $health->pesos,
            'pension_total_pesos' => $pension->pesos,
            'arl_total_pesos' => $arl->pesos,
            'ccf_total_pesos' => 0,
            'admin_fee_pesos' => $adminFee,
            'pension_weeks' => $weeks,
        ];
    }

    public function isCurrentPeriod(): bool
    {
        return false; // Pago VENCIDO
    }

    /** RF-058: Normalizar días a semanas válidas (7, 14, 21, 30). */
    private function daysToWeeks(int $days): int
    {
        return match (true) {
            $days <= 7 => 1,
            $days <= 14 => 2,
            $days <= 21 => 3,
            default => 4,
        };
    }
}
