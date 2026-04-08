<?php

namespace App\Modules\Affiliations\Services;

use App\Modules\Affiliates\Models\Affiliate;
use App\Modules\Affiliations\Models\MultiIncomeContract;
use App\Modules\RegulatoryEngine\Repositories\RegulatoryParameterRepository;
use App\Modules\RegulatoryEngine\Services\RoundingEngine;
use App\Modules\RegulatoryEngine\ValueObjects\Periodo;
use Illuminate\Support\Collection;

/**
 * Contratos multi-ingreso para independientes (tipo cotizante 03/16/57).
 *
 * RF-030: IBC = roundIBC(ingreso_reportado × 40%).
 * Tope consolidado: 25 SMMLV (D.1273/2018 Art. 4).
 * Los múltiples contratos del mismo período se suman para el IBC total.
 *
 * @see DOCUMENTO_RECTOR §3.3, RF-030, D.1273/2018
 */
final class MultiIncomeContractService
{
    /** RF-030: tope IBC en SMMLV para independientes multi-ingreso. */
    private const MAX_IBC_SMMLV = 25;

    /** RF-030: proporción de ingreso que constituye IBC (40%). */
    private const IBC_RATIO = 0.40;

    public function __construct(
        private readonly RegulatoryParameterRepository $params,
    ) {}

    /**
     * Registra un contrato de ingreso para el período.
     * Calcula el IBC individual al 40% del ingreso, respetando el tope 25 SMMLV
     * sobre el consolidado del período.
     *
     * @see DOCUMENTO_RECTOR §3.3, RF-030
     */
    public function addContract(
        Affiliate $affiliate,
        Periodo $period,
        int $incomePesos,
        ?string $description = null,
        ?int $createdBy = null,
    ): MultiIncomeContract {
        $onDate = sprintf('%04d-%02d-01', $period->year, $period->month);
        $smmlv = (int) ($this->params->valueAt('monetary', 'SMMLV', $onDate) ?? 1_423_500);
        $maxIbcPesos = self::MAX_IBC_SMMLV * $smmlv;

        // RF-030: IBC individual = roundIBC(ingreso × 40%)
        $rawIbc = (int) floor($incomePesos * self::IBC_RATIO);
        $ibcIndividual = RoundingEngine::roundIBC($rawIbc);

        // Tope consolidado: no superar (25 SMMLV - ya aportado en el período)
        $alreadyConsolidated = $this->consolidatedIbc($affiliate, $period);
        $remaining = max(0, $maxIbcPesos - $alreadyConsolidated);
        $ibcFinal = min($ibcIndividual, $remaining);

        return MultiIncomeContract::query()->create([
            'affiliate_id' => $affiliate->id,
            'period_year' => $period->year,
            'period_month' => $period->month,
            'contract_description' => $description,
            'income_pesos' => $incomePesos,
            'ibc_contribution_pesos' => $ibcFinal,
            'created_by' => $createdBy,
        ]);
    }

    /**
     * IBC consolidado de todos los contratos del período para este afiliado.
     * Valor a usar como input del motor PILA.
     */
    public function consolidatedIbc(Affiliate $affiliate, Periodo $period): int
    {
        return (int) MultiIncomeContract::query()
            ->where('affiliate_id', $affiliate->id)
            ->where('period_year', $period->year)
            ->where('period_month', $period->month)
            ->sum('ibc_contribution_pesos');
    }

    /**
     * Lista de contratos del período.
     *
     * @return Collection<int, MultiIncomeContract>
     */
    public function forPeriod(Affiliate $affiliate, Periodo $period): Collection
    {
        return MultiIncomeContract::query()
            ->where('affiliate_id', $affiliate->id)
            ->where('period_year', $period->year)
            ->where('period_month', $period->month)
            ->get();
    }
}
