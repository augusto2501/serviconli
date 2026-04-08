<?php

namespace App\Modules\Affiliates\Services;

// RF-010 — acciones post-registro

use App\Modules\Advisors\Services\AdvisorCommissionService;
use App\Modules\Affiliates\Models\Affiliate;
use App\Modules\Affiliates\Models\EnrollmentProcess;
use App\Modules\Affiliates\Models\ReentryProcess;
use App\Modules\Billing\Models\BillInvoice;
use App\Modules\Communications\Services\WhatsAppOutboundService;

final class PostEnrollmentCompletionService
{
    public function __construct(
        private readonly AdvisorCommissionService $advisorCommissionService,
        private readonly ThirdPartyProvisioningService $thirdPartyProvisioningService,
        private readonly WhatsAppOutboundService $whatsAppOutboundService,
    ) {}

    /**
     * Punto único para: recibo de caja, PDF contrato, comisión asesor, tercero contable, WhatsApp.
     */
    public function handle(EnrollmentProcess $process, Affiliate $affiliate): void
    {
        $this->advisorCommissionService->recordNewAffiliation($process, $affiliate);
        $this->thirdPartyProvisioningService->ensureForAffiliate($affiliate);
        $affiliate->loadMissing('person');
        $this->whatsAppOutboundService->sendTemplate($affiliate, 'welcome', [
            'name' => (string) ($affiliate->person?->first_name ?? 'afiliado'),
        ]);
    }

    public function handleReentry(
        ReentryProcess $process,
        Affiliate $affiliate,
        BillInvoice $invoice,
        string $paymentMethod,
    ): void {
        $this->advisorCommissionService->recordReentry($process, $affiliate, $invoice, $paymentMethod);
        $this->thirdPartyProvisioningService->ensureForAffiliate($affiliate);
        $affiliate->loadMissing('person');
        $this->whatsAppOutboundService->sendTemplate($affiliate, 'confirmation', [
            'name' => (string) ($affiliate->person?->first_name ?? 'afiliado'),
        ]);
    }
}
