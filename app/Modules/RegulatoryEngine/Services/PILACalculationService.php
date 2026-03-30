<?php

namespace App\Modules\RegulatoryEngine\Services;

use App\Modules\RegulatoryEngine\DTOs\CalculationInputDTO;
use App\Modules\RegulatoryEngine\DTOs\CalculationResultDTO;
use App\Modules\RegulatoryEngine\Enums\SubsystemType;
use App\Modules\RegulatoryEngine\Exceptions\MissingRegulatoryParameterException;
use App\Modules\RegulatoryEngine\Models\ContributorType;
use App\Modules\RegulatoryEngine\Repositories\RegulatoryParameterRepository;
use App\Modules\RegulatoryEngine\ValueObjects\IBC;

/**
 * Orquestador de liquidación PILA. Tarifas desde cfg_*; mora §3.6; solidaridad §3.5.
 */
final class PILACalculationService
{
    public function __construct(
        private readonly ?OperationalExceptionService $operationalExceptions = null,
        private readonly ?RegulatoryParameterRepository $regulatoryParameters = null,
        private readonly ?MoraInterestService $moraInterest = null,
        private readonly ?SolidarityFundCalculator $solidarityFundCalculator = null,
    ) {}

    public function calculate(
        CalculationInputDTO $input,
        ?string $targetType = null,
        ?int $targetId = null,
        ?string $onDate = null,
        int $daysLate = 0,
    ): CalculationResultDTO {
        $ibc = IBC::fromRaw($input->rawIbcPesos)->roundToMillarSuperior();
        $subsystemAmounts = [];
        $moraExempt = false;
        $date = $onDate ?? sprintf('%04d-%02d-01', $input->cotizationPeriod->year, $input->cotizationPeriod->month);
        $healthRatePercent = $this->requiredRate('rates', 'SALUD_TOTAL_PERCENT', $date);
        $pensionRatePercent = $this->requiredRate('rates', 'PENSION_TOTAL_PERCENT', $date);
        $arlRatePercent = $this->requiredRate('rates', $this->arlRateKeyForClass($input->arlRiskClass), $date);
        $ccfRatePercent = $this->ccfRateForContributorType($input->contributorTypeCode, $date);
        $moraRatePercent = $this->requiredRate('mora', 'DAILY_RATE_PERCENT', $date);

        if ($targetType !== null && $targetId !== null) {
            $service = $this->operationalExceptions ?? new OperationalExceptionService;

            $moraExempt = $service->isMoraExempt($targetType, $targetId, $date);
            $moraRatePercent = $service->moraRateOverridePercent($targetType, $targetId, $date) ?? $moraRatePercent;
        }

        $moraSvc = $this->moraInterest ?? new MoraInterestService;
        $moraInterest = $moraSvc->interestPesos($ibc->valueInPesos, $daysLate, $moraExempt, $moraRatePercent);

        $solidarityPesos = 0;
        $solidarityRatePercent = null;
        $solidarityMinSmmlv = null;
        $repo = $this->regulatoryParameters;
        if ($repo !== null) {
            $solCalc = $this->solidarityFundCalculator ?? new SolidarityFundCalculator($repo);
            $sol = $solCalc->compute($ibc->valueInPesos, $date);
            $solidarityPesos = $sol['pesos'];
            $solidarityRatePercent = $sol['rate_percent'];
            $solidarityMinSmmlv = $sol['min_smmlv_bracket'];
        }

        $healthTotal = $this->subsystemApplies($input->contributorTypeCode, SubsystemType::SALUD)
            ? $this->percentOf($ibc->valueInPesos, $healthRatePercent)
            : 0;
        $pensionTotal = $this->subsystemApplies($input->contributorTypeCode, SubsystemType::PENSION)
            ? $this->percentOf($ibc->valueInPesos, $pensionRatePercent)
            : 0;
        $arlTotal = $this->subsystemApplies($input->contributorTypeCode, SubsystemType::ARL)
            ? $this->percentOf($ibc->valueInPesos, $arlRatePercent)
            : 0;
        $ccfTotal = $this->subsystemApplies($input->contributorTypeCode, SubsystemType::CCF)
            ? $this->percentOf($ibc->valueInPesos, $ccfRatePercent)
            : 0;
        $total = $healthTotal + $pensionTotal + $arlTotal + $ccfTotal + $moraInterest + $solidarityPesos;

        $subsystemAmounts['mora_exempt'] = $moraExempt;
        $subsystemAmounts['mora_rate_percent'] = $moraRatePercent;
        $subsystemAmounts['mora_interest_pesos'] = $moraInterest;
        $subsystemAmounts['health_rate_percent'] = $healthRatePercent;
        $subsystemAmounts['pension_rate_percent'] = $pensionRatePercent;
        $subsystemAmounts['arl_rate_percent'] = $arlRatePercent;
        $subsystemAmounts['health_total_pesos'] = $healthTotal;
        $subsystemAmounts['pension_total_pesos'] = $pensionTotal;
        $subsystemAmounts['arl_total_pesos'] = $arlTotal;
        $subsystemAmounts['ccf_total_pesos'] = $ccfTotal;
        $subsystemAmounts['ccf_rate_percent'] = $ccfRatePercent;
        $subsystemAmounts['solidarity_fund_pesos'] = $solidarityPesos;
        $subsystemAmounts['solidarity_rate_percent'] = $solidarityRatePercent;
        $subsystemAmounts['solidarity_min_smmlv_bracket'] = $solidarityMinSmmlv;

        return new CalculationResultDTO(
            ibcRoundedPesos: $ibc->valueInPesos,
            subsystemAmountsPesos: $subsystemAmounts,
            totalSocialSecurityPesos: $total,
        );
    }

    private function percentOf(int $base, float $percent): int
    {
        return (int) round($base * ($percent / 100));
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

    private function subsystemApplies(string $contributorTypeCode, SubsystemType $subsystem): bool
    {
        $contributorType = ContributorType::query()
            ->where('code', $contributorTypeCode)
            ->first();

        if ($contributorType === null) {
            return true;
        }

        $rules = $contributorType->subsystemsPivot()
            ->where('subsystem', $subsystem->value)
            ->get();

        if ($rules->isEmpty()) {
            return true;
        }

        return (bool) $rules->contains(static fn ($r): bool => (bool) $r->is_required);
    }
}
