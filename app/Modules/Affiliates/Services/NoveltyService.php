<?php

namespace App\Modules\Affiliates\Services;

use App\Modules\Affiliates\Models\Affiliate;
use App\Modules\Affiliates\Models\Novelty;
use App\Modules\Affiliations\Services\SocialSecurityProfileService;
use App\Modules\RegulatoryEngine\ValueObjects\Periodo;
use Illuminate\Support\Collection;

/**
 * Gestión de novedades PILA — RN-06, RF-061..RF-066.
 *
 * Novedades que afectan perfil SS post-guardado:
 *   TAE, TAP → cierran versión actual, abren nueva.
 *   VSP/VST → nuevo IBC, versiona perfil.
 *   RET tipo X → RETIRADO + alerta ARL.
 *   RET tipo P → sigue ACTIVO, marca retiro pensión.
 *   RET tipo R → sigue ACTIVO + alerta ARL.
 *
 * Portado de Access Form_005 NovTAE/TAP, RetiroD, Opción_VSP.
 *
 * @see DOCUMENTO_RECTOR §3.4, §5.2
 */
final class NoveltyService
{
    public function __construct(
        private readonly AffiliateStatusMachine $statusMachine,
        private readonly SocialSecurityProfileService $profileService,
    ) {}

    /**
     * Registra una novedad para un afiliado en un período.
     */
    public function register(
        Affiliate $affiliate,
        Periodo $period,
        string $noveltyTypeCode,
        array $data = [],
    ): Novelty {
        $novelty = Novelty::query()->create([
            'affiliate_id' => $affiliate->id,
            'payer_id' => $data['payer_id'] ?? null,
            'period_year' => $period->year,
            'period_month' => $period->month,
            'novelty_type_code' => $noveltyTypeCode,
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
            'previous_entity_id' => $data['previous_entity_id'] ?? null,
            'new_entity_id' => $data['new_entity_id'] ?? null,
            'previous_value' => $data['previous_value'] ?? null,
            'new_value' => $data['new_value'] ?? null,
            'retirement_scope' => $data['retirement_scope'] ?? null,
            'retirement_cause' => $data['retirement_cause'] ?? null,
            'notes' => $data['notes'] ?? null,
            'created_by' => $data['created_by'] ?? null,
        ]);

        $this->processPostSaveEffects($affiliate, $novelty);

        return $novelty;
    }

    /**
     * Obtiene novedades de un afiliado para un período.
     */
    public function forPeriod(Affiliate $affiliate, Periodo $period): Collection
    {
        return Novelty::query()
            ->where('affiliate_id', $affiliate->id)
            ->where('period_year', $period->year)
            ->where('period_month', $period->month)
            ->get();
    }

    /**
     * Efectos post-guardado — Portado de Form_005 Btn_Guardar.
     *
     * @see DOCUMENTO_RECTOR §3.4 — "Novedades que afectan perfil SS post-guardado"
     */
    private function processPostSaveEffects(Affiliate $affiliate, Novelty $novelty): void
    {
        match ($novelty->novelty_type_code) {
            'TAE' => $this->processTransferEPS($affiliate, $novelty),
            'TAP' => $this->processTransferAFP($affiliate, $novelty),
            'VSP', 'VST' => $this->processSalaryChange($affiliate, $novelty),
            'RET' => $this->processRetirement($affiliate, $novelty),
            default => null,
        };
    }

    /** RF-065: Traslado EPS — versiona perfil SS con nueva entidad. */
    private function processTransferEPS(Affiliate $affiliate, Novelty $novelty): void
    {
        if ($novelty->new_entity_id === null) {
            return;
        }

        $this->profileService->versionProfileForTransfer(
            $affiliate,
            'eps_entity_id',
            $novelty->new_entity_id,
        );
    }

    /** RF-065: Traslado AFP — versiona perfil SS con nueva entidad. */
    private function processTransferAFP(Affiliate $affiliate, Novelty $novelty): void
    {
        if ($novelty->new_entity_id === null) {
            return;
        }

        $this->profileService->versionProfileForTransfer(
            $affiliate,
            'afp_entity_id',
            $novelty->new_entity_id,
        );
    }

    /** RF-066: Variación de salario — versiona perfil SS con nuevo IBC. */
    private function processSalaryChange(Affiliate $affiliate, Novelty $novelty): void
    {
        if ($novelty->new_value === null) {
            return;
        }

        $this->profileService->versionProfileForSalaryChange(
            $affiliate,
            (int) $novelty->new_value,
        );
    }

    /**
     * RF-062: Retiro — 3 tipos (X, P, R).
     *   X = retiro total → RETIRADO + alerta ARL [RN-28]
     *   P = solo pensión → sigue ACTIVO
     *   R = solo ARL → sigue ACTIVO + alerta ARL [RN-28]
     */
    private function processRetirement(Affiliate $affiliate, Novelty $novelty): void
    {
        match ($novelty->retirement_scope) {
            'TOTAL' => $this->statusMachine->retire($affiliate),
            'PENSION_ONLY', 'ARL_ONLY' => null, // Sigue ACTIVO
            default => null,
        };
    }

    /**
     * RN-28: ¿Requiere alerta de retiro ARL?
     * Retiro tipo X o R → "Recuerde retirar al afiliado en la plataforma de la ARL".
     */
    public function requiresARLRetirementAlert(Novelty $novelty): bool
    {
        return $novelty->novelty_type_code === 'RET'
            && in_array($novelty->retirement_scope, ['TOTAL', 'ARL_ONLY'], true);
    }
}
