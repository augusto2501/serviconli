<?php

namespace App\Modules\Documents\Services;

use App\Modules\Affiliates\Models\Affiliate;
use App\Modules\Affiliates\Services\PaymentCertificateService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * RF-103 — generación PDF de plantillas de contrato / certificados.
 */
final class ContractPdfService
{
    public function __construct(
        private readonly PaymentCertificateService $paymentCertificateService,
    ) {}

    public function download(Affiliate $affiliate, string $code, Request $request): JsonResponse|StreamedResponse
    {
        if ($code === 'payment_certificate') {
            return $this->paymentCertificatePdf($affiliate, $request);
        }

        if (! in_array($code, ContractTemplateRegistry::codes(), true)) {
            return response()->json(['message' => 'Plantilla no reconocida.'], 404);
        }

        $view = ContractTemplateRegistry::viewFor($code);
        if ($view === null || $view === '') {
            return response()->json(['message' => 'Plantilla no configurada.'], 500);
        }

        $affiliate->loadMissing('person', 'status');
        $vars = array_merge($this->commonVars($affiliate), [
            'templateVersion' => ContractTemplateRegistry::versionFor($code),
            'generatedAt' => now(),
        ]);

        $pdf = Pdf::loadView($view, $vars)->setPaper('a4', 'portrait');
        $filename = sprintf('%s-v%d-afiliado-%s.pdf', $code, ContractTemplateRegistry::versionFor($code), $affiliate->id);

        return response()->streamDownload(
            static function () use ($pdf): void {
                echo $pdf->output();
            },
            $filename,
            [
                'Content-Type' => 'application/pdf',
                'X-Contract-Template-Version' => (string) ContractTemplateRegistry::versionFor($code),
            ],
        );
    }

    private function paymentCertificatePdf(Affiliate $affiliate, Request $request): JsonResponse|StreamedResponse
    {
        $v = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'format' => ['sometimes', 'string', 'in:full,summary'],
        ]);

        $format = $v['format'] ?? 'full';
        $data = $this->paymentCertificateService->forPeriod($affiliate, (int) $v['year'], (int) $v['month']);

        if (! $data['paid'] || $data['line'] === null) {
            return response()->json(['message' => $data['message']], 422);
        }

        $view = ContractTemplateRegistry::paymentCertificateView($format);

        return $this->paymentCertificateService->downloadPdf($affiliate, $data, $view, ContractTemplateRegistry::versionFor('payment_certificate'), $format);
    }

    /** @return array<string, mixed> */
    private function commonVars(Affiliate $affiliate): array
    {
        $person = $affiliate->person;
        $personName = trim(implode(' ', array_filter([
            $person?->first_name,
            $person?->second_name,
            $person?->first_surname,
            $person?->second_surname,
        ])));

        $documentNumber = trim((string) ($person?->document_type ?? '').' '.(string) ($person?->document_number ?? ''));

        return [
            'affiliateId' => $affiliate->id,
            'personName' => $personName !== '' ? $personName : 'Afiliado #'.$affiliate->id,
            'documentNumber' => $documentNumber,
            'statusCode' => $affiliate->status?->code,
            'clientType' => $affiliate->client_type?->value ?? (string) $affiliate->client_type,
        ];
    }
}
