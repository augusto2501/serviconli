<?php

namespace App\Modules\Affiliates\Services;

use App\Modules\Affiliates\Events\MoraBeneficiaryAlertNeeded;
use App\Modules\Affiliates\Models\Affiliate;
use App\Modules\RegulatoryEngine\Models\AffiliateStatus;
use InvalidArgumentException;

/**
 * Máquina de estados de mora escalonada — RN-05, RF-071..RF-074.
 *
 * Niveles (sort_order ascendente):
 *   AFILIADO → ACTIVO → SUSPENDIDO → MORA_30 → MORA_60 → MORA_90 → MORA_120 → MORA_120_PLUS → RETIRADO
 *
 * Reglas:
 *   - Sin pago en un período → sube UN nivel (escalate)
 *   - Con pago → baja UN nivel (deescalate). NO salta directo a ACTIVO.
 *   - Mora > 1 mes → alerta beneficiarios (D.780/2016)
 *
 * Portado de Access Form_005 máquina de estados.
 *
 * @see DOCUMENTO_RECTOR §5.4, RN-05
 */
final class AffiliateStatusMachine
{
    /**
     * Orden de escalamiento de mora (del más bajo al más alto).
     * AFILIADO se trata como punto de entrada, RETIRADO es terminal.
     */
    private const ESCALATION_ORDER = [
        'AFILIADO',
        'ACTIVO',
        'PAGO_MES_SUBSIGUIENTE',
        'SUSPENDIDO',
        'MORA_30',
        'MORA_60',
        'MORA_90',
        'MORA_120',
        'MORA_120_PLUS',
    ];

    /**
     * Escala un nivel de mora (sin pago en el período).
     * RF-072: incrementa UN solo nivel.
     */
    public function escalate(Affiliate $affiliate): string
    {
        $currentCode = $this->currentStatusCode($affiliate);
        $index = $this->indexOfCode($currentCode);

        if ($index === false) {
            return $currentCode; // RETIRADO u otro estado terminal
        }

        $nextIndex = $index + 1;
        if ($nextIndex >= count(self::ESCALATION_ORDER)) {
            return 'MORA_120_PLUS'; // Tope
        }

        // Saltar PAGO_MES_SUBSIGUIENTE en escalamiento
        $nextCode = self::ESCALATION_ORDER[$nextIndex];
        if ($nextCode === 'PAGO_MES_SUBSIGUIENTE') {
            $nextCode = self::ESCALATION_ORDER[$nextIndex + 1] ?? 'SUSPENDIDO';
        }

        $this->transitionTo($affiliate, $nextCode);

        return $nextCode;
    }

    /**
     * Desescala un nivel de mora (con pago).
     * RF-073: reduce UN solo nivel. NO salta a ACTIVO directamente.
     */
    public function deescalate(Affiliate $affiliate): string
    {
        $currentCode = $this->currentStatusCode($affiliate);
        $index = $this->indexOfCode($currentCode);

        if ($index === false || $index <= 0) {
            return $currentCode;
        }

        $prevCode = self::ESCALATION_ORDER[$index - 1];

        // Saltar PAGO_MES_SUBSIGUIENTE en desescalamiento normal
        if ($prevCode === 'PAGO_MES_SUBSIGUIENTE') {
            $prevCode = self::ESCALATION_ORDER[$index - 2] ?? 'ACTIVO';
        }

        $this->transitionTo($affiliate, $prevCode);

        return $prevCode;
    }

    /**
     * Marca el primer pago después de afiliación → ACTIVO.
     */
    public function activateOnFirstPayment(Affiliate $affiliate): void
    {
        $this->transitionTo($affiliate, 'ACTIVO');
    }

    /**
     * Fuerza RETIRADO — para retiro total (tipo X).
     */
    public function retire(Affiliate $affiliate): void
    {
        $this->transitionTo($affiliate, 'RETIRADO');
    }

    /**
     * RF-052: Marca período adelantado.
     */
    public function markAdvancePeriod(Affiliate $affiliate): void
    {
        $this->transitionTo($affiliate, 'PAGO_MES_SUBSIGUIENTE');
    }

    /**
     * RF-074: ¿Mora supera 1 mes? → alerta beneficiarios.
     */
    public function requiresBeneficiaryAlert(Affiliate $affiliate): bool
    {
        return $this->isInBeneficiaryAlertTier($this->currentStatusCode($affiliate));
    }

    /**
     * ¿El afiliado está en algún nivel de mora?
     */
    public function isInMora(Affiliate $affiliate): bool
    {
        $code = $this->currentStatusCode($affiliate);

        return str_starts_with($code, 'MORA_') || $code === 'SUSPENDIDO';
    }

    public function currentStatusCode(Affiliate $affiliate): string
    {
        $status = $affiliate->status;

        return $status?->code ?? 'AFILIADO';
    }

    private function transitionTo(Affiliate $affiliate, string $statusCode): void
    {
        $previousCode = $this->currentStatusCode($affiliate);

        $status = AffiliateStatus::query()->where('code', $statusCode)->first();

        if ($status === null) {
            throw new InvalidArgumentException("Estado de afiliado no encontrado: {$statusCode}");
        }

        $affiliate->status_id = $status->id;
        $affiliate->mora_status = $this->moraStatusFromCode($statusCode);
        $affiliate->save();
        $affiliate->load('status');

        $newCode = $this->currentStatusCode($affiliate);
        if (! $this->isInBeneficiaryAlertTier($previousCode) && $this->isInBeneficiaryAlertTier($newCode)) {
            MoraBeneficiaryAlertNeeded::dispatch($affiliate->fresh(['status', 'person']));
        }
    }

    private function isInBeneficiaryAlertTier(string $code): bool
    {
        return in_array($code, ['MORA_60', 'MORA_90', 'MORA_120', 'MORA_120_PLUS'], true);
    }

    private function moraStatusFromCode(string $code): string
    {
        return match ($code) {
            'AFILIADO', 'ACTIVO', 'PAGO_MES_SUBSIGUIENTE' => 'AL_DIA',
            'SUSPENDIDO' => 'SUSPENDIDO',
            default => str_starts_with($code, 'MORA_') ? 'EN_MORA' : 'AL_DIA',
        };
    }

    private function indexOfCode(string $code): int|false
    {
        return array_search($code, self::ESCALATION_ORDER, true);
    }
}
