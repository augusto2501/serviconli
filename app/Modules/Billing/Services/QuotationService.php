<?php

namespace App\Modules\Billing\Services;

use App\Modules\Billing\Models\Quotation;
use App\Modules\RegulatoryEngine\DTOs\CalculationInputDTO;
use App\Modules\RegulatoryEngine\Services\PILACalculationService;
use App\Modules\RegulatoryEngine\ValueObjects\Periodo;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Cotizador con mismas fórmulas que liquidación — RN-19, RN-14, RF-053, RF-054.
 *
 * @see DOCUMENTO_RECTOR §4.7, RF-053, RF-054
 */
final class QuotationService
{
    /**
     * RF-054: Etiquetas para el PDF — claves alineadas con {@see PILACalculationService::calculateFull}.
     */
    private const SUBSYSTEM_LABELS = [
        'health_total_pesos' => 'Salud (EPS)',
        'pension_total_pesos' => 'Pensión (AFP)',
        'arl_total_pesos' => 'Riesgos laborales (ARL)',
        'ccf_total_pesos' => 'Caja de compensación (CCF)',
        'solidarity_fund_pesos' => 'Fondo de solidaridad pensional',
    ];

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

        $quotation = Quotation::query()->create([
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

        // RF-054: Generar PDF con branding Serviconli y almacenar en storage
        $pdfPath = $this->generatePdf($quotation);
        $quotation->pdf_path = $pdfPath;
        $quotation->save();

        return $quotation;
    }

    /**
     * RF-054: Genera el PDF de cotización y lo persiste en storage/app/quotations/.
     * Retorna la ruta relativa almacenada en bill_quotations.pdf_path.
     *
     * @see DOCUMENTO_RECTOR §4.7
     */
    public function generatePdf(Quotation $quotation): string
    {
        $lineItems = [];
        foreach (self::SUBSYSTEM_LABELS as $amountKey => $label) {
            $v = $quotation->amounts[$amountKey] ?? null;
            if (is_numeric($v) && (int) $v > 0) {
                $lineItems[$label] = (int) $v;
            }
        }

        $pdf = Pdf::loadView('pdf.quotation', [
            'quotation' => $quotation,
            'lineItems' => $lineItems,
        ])->setPaper('a4', 'portrait');

        $filename = sprintf('quotations/COT-%04d.pdf', $quotation->id);
        Storage::put($filename, $pdf->output());

        return $filename;
    }
}
