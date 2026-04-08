<?php

namespace App\Modules\Billing\Services;

use App\Modules\Billing\Models\Quotation;
use App\Modules\RegulatoryEngine\DTOs\CalculationInputDTO;
use App\Modules\RegulatoryEngine\Services\PILACalculationService;
use App\Modules\RegulatoryEngine\ValueObjects\Periodo;
use Illuminate\Support\Carbon;

/**
 * Cotizador con mismas fórmulas que liquidación — RN-19, RN-14.
 */
final class QuotationService
{
    public function __construct(
        private readonly PILACalculationService $pila,
    ) {}

    /**
     * @param  array{
     *   prospect_name: string,
     *   prospect_document?: string|null,
     *   prospect_phone?: string|null,
     *   prospect_email?: string|null,
     *   salary_pesos: int,
     *   contributor_type_code: string,
     *   arl_risk_class?: int,
     *   period_year?: int|null,
     *   period_month?: int|null,
     * }  $data
     */
    public function create(array $data, ?int $createdById = null): Quotation
    {
        $year = (int) ($data['period_year'] ?? Carbon::now()->year);
        $month = (int) ($data['period_month'] ?? Carbon::now()->month);
        $period = new Periodo($year, $month);
        $onDate = sprintf('%04d-%02d-01', $period->year, $period->month);

        $arl = max(1, min(5, (int) ($data['arl_risk_class'] ?? 1)));

        $input = new CalculationInputDTO(
            rawIbcPesos: (int) $data['salary_pesos'],
            cotizationPeriod: $period,
            contributorTypeCode: (string) $data['contributor_type_code'],
            arlRiskClass: $arl,
        );

        $result = $this->pila->calculate($input, null, null, $onDate, 0);

        $amounts = array_merge($result->subsystemAmountsPesos, [
            'ibcRoundedPesos' => $result->ibcRoundedPesos,
            'totalSocialSecurityPesos' => $result->totalSocialSecurityPesos,
        ]);

        return Quotation::query()->create([
            'prospect_name' => $data['prospect_name'],
            'prospect_document' => $data['prospect_document'] ?? null,
            'prospect_phone' => $data['prospect_phone'] ?? null,
            'prospect_email' => $data['prospect_email'] ?? null,
            'salary_pesos' => (int) $data['salary_pesos'],
            'contributor_type_code' => (string) $data['contributor_type_code'],
            'arl_risk_class' => $arl,
            'amounts' => $amounts,
            'pdf_path' => null,
            'created_by' => $createdById,
        ]);
    }
}
