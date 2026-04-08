<?php

namespace App\Modules\Affiliates\Services;

use App\Modules\Affiliates\Models\Affiliate;
use App\Modules\PILALiquidation\Enums\PilaLiquidationStatus;
use App\Modules\PILALiquidation\Models\PilaLiquidationLine;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    /**
     * RN-22: PDF del certificado — usar tras comprobar {@see forPeriod} con `paid === true`.
     *
     * @param  array{period: array{year: int, month: int}, paid: bool, line: array<string, mixed>|null, message: string}  $certificateData
     */
    public function downloadPdf(Affiliate $affiliate, array $certificateData): StreamedResponse
    {
        if (! $certificateData['paid'] || $certificateData['line'] === null) {
            throw new \InvalidArgumentException('Certificado PDF solo con PILA confirmada para el período.');
        }

        $data = $certificateData;
        $affiliate->loadMissing('person');
        $person = $affiliate->person;
        $personName = trim(implode(' ', array_filter([
            $person?->first_name,
            $person?->second_name,
            $person?->first_surname,
            $person?->second_surname,
        ])));

        $documentNumber = trim((string) ($person?->document_type ?? '').' '.(string) ($person?->document_number ?? ''));

        $pdf = Pdf::loadView('pdf.payment-certificate', [
            'period' => $data['period'],
            'line' => $data['line'],
            'message' => $data['message'],
            'personName' => $personName !== '' ? $personName : 'Afiliado #'.$affiliate->id,
            'documentNumber' => $documentNumber,
        ])->setPaper('a4', 'portrait');

        $y = $data['period']['year'];
        $m = $data['period']['month'];
        $filename = sprintf('certificado-pago-%s-%04d-%02d.pdf', $affiliate->id, $y, $m);

        return response()->streamDownload(
            static function () use ($pdf): void {
                echo $pdf->output();
            },
            $filename,
            ['Content-Type' => 'application/pdf'],
        );
    }
}
