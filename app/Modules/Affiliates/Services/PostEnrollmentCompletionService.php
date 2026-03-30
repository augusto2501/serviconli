<?php

namespace App\Modules\Affiliates\Services;

// RF-010 — acciones post-registro (integraciones pendientes)

use App\Modules\Affiliates\Models\Affiliate;
use App\Modules\Affiliates\Models\EnrollmentProcess;

final class PostEnrollmentCompletionService
{
    /**
     * Punto único para: recibo de caja, PDF contrato, comisión asesor, tercero contable, WhatsApp.
     * Las integraciones con facturación, plantillas PDF y canales externos se enlazan aquí.
     */
    public function handle(EnrollmentProcess $process, Affiliate $affiliate): void
    {
        // RF-010 — stub intencional: despachar jobs/eventos cuando existan servicios aguas abajo.
    }
}
