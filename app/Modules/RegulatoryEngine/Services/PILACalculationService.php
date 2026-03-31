<?php

namespace App\Modules\RegulatoryEngine\Services;

use App\Modules\RegulatoryEngine\DTOs\CalculationContext;
use App\Modules\RegulatoryEngine\DTOs\CalculationInputDTO;
use App\Modules\RegulatoryEngine\DTOs\CalculationResultDTO;
use App\Modules\RegulatoryEngine\Exceptions\MissingRegulatoryParameterException;
use App\Modules\RegulatoryEngine\Repositories\RegulatoryParameterRepository;
use App\Modules\RegulatoryEngine\Strategies\StrategyResolver;
use App\Modules\RegulatoryEngine\ValueObjects\IBC;

/**
 * Orquestador de liquidación PILA — 11 pasos (Sec. 3.1).
 *
 * Delega cálculo de subsistemas a Strategy por tipo cotizante (RF-042).
 * Tarifas desde cfg_regulatory_parameters; mora §8.3; solidaridad §3.5.
 *
 * @see DOCUMENTO_RECTOR §3.1, RF-031..RF-044
 */
final class PILACalculationService
{
    public function __construct(
        private readonly ?OperationalExceptionService $operationalExceptions = null,
        private readonly ?RegulatoryParameterRepository $regulatoryParameters = null,
        private readonly ?MoraInterestService $moraInterest = null,
        private readonly ?SolidarityFundCalculator $solidarityFundCalculator = null,
        private readonly ?StrategyResolver $strategyResolver = null,
    ) {}

    /**
     * Cálculo completo usando CalculationContext (nuevo flujo con Strategy).
     */
    public function calculateFull(
        CalculationContext $context,
        ?string $targetType = null,
        ?int $targetId = null,
        int $daysLate = 0,
    ): CalculationResultDTO {
        $resolver = $this->strategyResolver ?? new StrategyResolver;
        $strategy = $resolver->resolve($context->contributorTypeCode);
        $date = $context->referenceDate;

        $strategyResult = $strategy->calculate($context);

        $ibcSalud = $strategyResult['ibc_salud_pesos'] ?? 0;
        $healthTotal = $strategyResult['health_total_pesos'] ?? 0;
        $pensionTotal = $strategyResult['pension_total_pesos'] ?? 0;
        $arlTotal = $strategyResult['arl_total_pesos'] ?? 0;
        $ccfTotal = $strategyResult['ccf_total_pesos'] ?? 0;
        $adminFee = $strategyResult['admin_fee_pesos'] ?? 0;

        // Solidaridad — §3.5
        $solidarityPesos = 0;
        $solidarityRatePercent = null;
        $solidarityMinSmmlv = null;
        if ($this->regulatoryParameters !== null) {
            $solCalc = $this->solidarityFundCalculator ?? new SolidarityFundCalculator($this->regulatoryParameters);
            $sol = $solCalc->compute($ibcSalud, $date);
            $solidarityPesos = $sol['pesos'];
            $solidarityRatePercent = $sol['rate_percent'];
            $solidarityMinSmmlv = $sol['min_smmlv_bracket'];
        }

        // Paso 9: TotalAportePOS = salud + pensión + ARL + CCF + solidaridad
        $totalAportePOS = $healthTotal + $pensionTotal + $arlTotal + $ccfTotal + $solidarityPesos;

        // Excepciones operativas de mora
        $moraExempt = false;
        $monthlyMoraRate = $this->resolveMonthlyMoraRate($date);

        if ($targetType !== null && $targetId !== null) {
            $svc = $this->operationalExceptions ?? new OperationalExceptionService;
            $moraExempt = $svc->isMoraExempt($targetType, $targetId, $date);
            $dailyOverride = $svc->moraRateOverridePercent($targetType, $targetId, $date);
            if ($dailyOverride !== null) {
                $monthlyMoraRate = $dailyOverride * 30;
            }
        }

        // Paso 10: Mora — base = TotalAportePOS (NO admin, NO afiliación)
        $moraSvc = $this->moraInterest ?? new MoraInterestService;
        $moraInterest = $moraSvc->interestPesos($totalAportePOS, $daysLate, $moraExempt, $monthlyMoraRate);

        // Paso 11: TotalPago = TotalAportePOS + Admin + Mora
        $totalPago = $totalAportePOS + $adminFee + $moraInterest;

        return new CalculationResultDTO(
            ibcRoundedPesos: $ibcSalud,
            subsystemAmountsPesos: [
                'ibc_salud_pesos' => $ibcSalud,
                'ibc_pension_pesos' => $strategyResult['ibc_pension_pesos'] ?? $ibcSalud,
                'health_rate_percent' => $context->healthRatePercent,
                'pension_rate_percent' => $context->pensionRatePercent,
                'arl_rate_percent' => $context->arlRatePercent,
                'ccf_rate_percent' => $context->ccfRatePercent,
                'health_total_pesos' => $healthTotal,
                'pension_total_pesos' => $pensionTotal,
                'arl_total_pesos' => $arlTotal,
                'ccf_total_pesos' => $ccfTotal,
                'solidarity_fund_pesos' => $solidarityPesos,
                'solidarity_rate_percent' => $solidarityRatePercent,
                'solidarity_min_smmlv_bracket' => $solidarityMinSmmlv,
                'total_aporte_pos_pesos' => $totalAportePOS,
                'admin_fee_pesos' => $adminFee,
                'mora_exempt' => $moraExempt,
                'mora_monthly_rate_percent' => $monthlyMoraRate,
                'mora_interest_pesos' => $moraInterest,
            ],
            totalSocialSecurityPesos: $totalPago,
        );
    }

    /**
     * Cálculo simplificado (mantiene compatibilidad con API existente).
     * Usa el DTO original — internamente delega a calculateFull.
     */
    public function calculate(
        CalculationInputDTO $input,
        ?string $targetType = null,
        ?int $targetId = null,
        ?string $onDate = null,
        int $daysLate = 0,
    ): CalculationResultDTO {
        $date = $onDate ?? sprintf('%04d-%02d-01', $input->cotizationPeriod->year, $input->cotizationPeriod->month);
        $ibc = IBC::fromRaw($input->rawIbcPesos)->roundToMillarSuperior();

        $healthRate = $this->requiredRate('rates', 'SALUD_TOTAL_PERCENT', $date);
        $pensionRate = $this->requiredRate('rates', 'PENSION_TOTAL_PERCENT', $date);
        $arlRate = $this->requiredRate('rates', $this->arlRateKeyForClass($input->arlRiskClass), $date);
        $ccfRate = $this->ccfRateForContributorType($input->contributorTypeCode, $date);

        $context = new CalculationContext(
            salaryPesos: $ibc->valueInPesos,
            daysEps: 30,
            daysAfp: 30,
            daysArl: 30,
            daysCcf: 30,
            cotizationPeriod: $input->cotizationPeriod,
            contributorTypeCode: $input->contributorTypeCode,
            arlRiskClass: $input->arlRiskClass,
            healthRatePercent: $healthRate,
            pensionRatePercent: $pensionRate,
            arlRatePercent: $arlRate,
            ccfRatePercent: $ccfRate,
            referenceDate: $date,
        );

        return $this->calculateFull($context, $targetType, $targetId, $daysLate);
    }

    private function ccfRateForContributorType(string $contributorTypeCode, string $date): float
    {
        $isDependent = in_array($contributorTypeCode, ['01', '02'], true);

        return $isDependent
            ? $this->requiredRate('rates', 'CCF_DEPENDIENTE_PERCENT', $date)
            : $this->requiredRate('rates', 'CCF_INDEPENDIENTE_PERCENT', $date);
    }

    private function arlRateKeyForClass(int $arlRiskClass): string
    {
        return match ($arlRiskClass) {
            1 => 'ARL_RISK_CLASS_I_PERCENT',
            2 => 'ARL_RISK_CLASS_II_PERCENT',
            3 => 'ARL_RISK_CLASS_III_PERCENT',
            4 => 'ARL_RISK_CLASS_IV_PERCENT',
            5 => 'ARL_RISK_CLASS_V_PERCENT',
            default => throw new MissingRegulatoryParameterException("Clase de riesgo ARL inválida: {$arlRiskClass}."),
        };
    }

    private function requiredRate(string $category, string $key, string $date): float
    {
        $repo = $this->regulatoryParameters;
        if ($repo === null) {
            throw MissingRegulatoryParameterException::for($category, $key, $date);
        }

        $value = $repo->valueAt($category, $key, $date);
        if (! is_numeric($value)) {
            throw MissingRegulatoryParameterException::for($category, $key, $date);
        }

        return (float) $value;
    }

    private function resolveMonthlyMoraRate(string $date): float
    {
        if ($this->regulatoryParameters === null) {
            return 2.5;
        }
        $daily = $this->regulatoryParameters->valueAt('mora', 'DAILY_RATE_PERCENT', $date);

        return is_numeric($daily) ? (float) $daily * 30 : 2.5;
    }
}
