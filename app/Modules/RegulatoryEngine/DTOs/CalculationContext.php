<?php

namespace App\Modules\RegulatoryEngine\DTOs;

use App\Modules\RegulatoryEngine\ValueObjects\IBC;
use App\Modules\RegulatoryEngine\ValueObjects\Periodo;

/**
 * Contexto completo para el cálculo de aportes PILA.
 * Contiene toda la información que las Strategies necesitan.
 *
 * @see DOCUMENTO_RECTOR §3.1 — 11 pasos de cálculo
 */
final readonly class CalculationContext
{
    public function __construct(
        public int $salaryPesos,
        public int $daysEps,
        public int $daysAfp,
        public int $daysArl,
        public int $daysCcf,
        public Periodo $cotizationPeriod,
        public string $contributorTypeCode,
        public int $subtipo = 0,
        public int $arlRiskClass = 1,
        public bool $isType51 = false,

        // Tarifas resueltas desde cfg_regulatory_parameters
        public float $healthRatePercent = 12.5,
        public float $pensionRatePercent = 16.0,
        public float $arlRatePercent = 0.522,
        public float $ccfRatePercent = 4.0,

        // Fee administración Serviconli
        public int $adminFeePesos = 0,

        // Referencia de fecha para parámetros con vigencia
        public string $referenceDate = '2026-01-01',
    ) {}

    /** IBC para EPS/Salud. Fórmula: roundIBC(Int((Salario/30) × DíasEPS)) */
    public function ibcSalud(): IBC
    {
        return IBC::calcular($this->salaryPesos, $this->daysEps);
    }

    /** IBC para AFP/Pensión (puede diferir si retiro tipo P). */
    public function ibcPension(): IBC
    {
        return IBC::calcular($this->salaryPesos, $this->daysAfp);
    }

    /** IBC para ARL. */
    public function ibcArl(): IBC
    {
        return IBC::calcular($this->salaryPesos, $this->daysArl);
    }

    /** IBC para CCF. */
    public function ibcCcf(): IBC
    {
        return IBC::calcular($this->salaryPesos, $this->daysCcf);
    }
}
