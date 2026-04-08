<?php

namespace App\Modules\Affiliates\Services;

// RF-010 — acciones post-registro

use App\Modules\Advisors\Services\AdvisorCommissionService;
use App\Modules\Affiliates\Models\Affiliate;
use App\Modules\Affiliates\Models\EnrollmentProcess;
use App\Modules\Affiliates\Models\ReentryProcess;
use App\Modules\Billing\Models\BillInvoice;

final class PostEnrollmentCompletionService
{
    public function __construct(
        private readonly AdvisorCommissionService $advisorCommissionService,
        private readonly ThirdPartyProvisioningService $thirdPartyProvisioningService,
    ) {}

    /**
     * Punto único para: recibo de caja, PDF contrato, comisión asesor, tercero contable, WhatsApp.
     */
    public function handle(EnrollmentProcess $process, Affiliate $affiliate): void
    {
        $this->advisorCommissionService->recordNewAffiliation($process, $affiliate);
        $this->thirdPartyProvisioningService->ensureForAffiliate($affiliate);
        $this->dispatchWelcomeWhatsAppStub($affiliate);
    }

    public function handleReentry(
        ReentryProcess $process,
        Affiliate $affiliate,
        BillInvoice $invoice,
        string $paymentMethod,
    ): void {
        $this->advisorCommissionService->recordReentry($process, $affiliate, $invoice, $paymentMethod);
        $this->thirdPartyProvisioningService->ensureForAffiliate($affiliate);
        $this->dispatchWelcomeWhatsAppStub($affiliate);
    }

    private function dispatchWelcomeWhatsAppStub(Affiliate $affiliate): void
    {
        // RF-010 / Sprint J-2 — integrar canal WhatsApp cuando exista el módulo Communications.
        unset($affiliate);
    }
}
