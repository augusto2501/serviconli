<?php

namespace App\Modules\Affiliates\Services;

use App\Modules\Affiliates\Events\ARLRetirementReminderRequested;
use App\Modules\Affiliates\Models\Affiliate;
use App\Modules\Affiliates\Models\Novelty;
use App\Modules\Affiliations\Services\SocialSecurityProfileService;
use App\Modules\RegulatoryEngine\ValueObjects\Periodo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;

/**
 * Gestión de novedades PILA — RN-06, RF-061..RF-066.
 *
 * 18 tipos soportados:
 *   ING → ACTIVO si venía de AFILIADO.
 *   TAE, TDE → cierran versión actual, abren nueva EPS.
 *   TAP, TDP → cierran versión actual, abren nueva AFP.
 *   VSP/VST   → nuevo IBC, versiona perfil.
 *   VTE       → nueva tarifa EPS, versiona perfil.
 *   VCT       → nueva clase riesgo ARL, versiona perfil.
 *   RET tipo X → RETIRADO + alerta ARL.
 *   RET tipo P → sigue ACTIVO, marca retiro pensión.
 *   RET tipo R → sigue ACTIVO + alerta ARL.
 *   LMA, LPA, IGE, IRL, SLN, LLU, AVP, COR → sin efecto perfil (cálculo PILA).
 *
 * Combinaciones inválidas: ING+TAE (no se puede trasladar EPS en mes de ingreso).
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
     * Combinaciones de novedades inválidas en el mismo período.
     * RF-061: ING+TAE → no se puede trasladar EPS en mes de ingreso.
     *
     * @see DOCUMENTO_RECTOR §3.4
     */
    private const INCOMPATIBLE_PAIRS = [
        ['ING', 'TAE'],
        ['ING', 'TDE'],
    ];

    /**
     * Registra una novedad para un afiliado en un período.
     * Valida combinaciones inválidas antes de guardar.
     */
    public function register(
        Affiliate $affiliate,
        Periodo $period,
        string $noveltyTypeCode,
        array $data = [],
    ): Novelty {
        // RF-061: Validar combinaciones inválidas
        $this->assertNoCombinationConflict($affiliate, $period, $noveltyTypeCode);

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
     * RF-061: 18 tipos de novedad con sus efectos sobre el perfil SS.
     *
     * @see DOCUMENTO_RECTOR §3.4 — "Novedades que afectan perfil SS post-guardado"
     */
    private function processPostSaveEffects(Affiliate $affiliate, Novelty $novelty): void
    {
        match ($novelty->novelty_type_code) {
            // Entrada: activa afiliado si venía de AFILIADO
            'ING' => $this->processIngreso($affiliate),
            // Traslado de entidad EPS (TAE = mismo depto, TDE = traslado departamento)
            'TAE', 'TDE' => $this->processTransferEPS($affiliate, $novelty),
            // Traslado de entidad AFP
            'TAP', 'TDP' => $this->processTransferAFP($affiliate, $novelty),
            // Variación de salario
            'VSP', 'VST' => $this->processSalaryChange($affiliate, $novelty),
            // Variación de tarifa EPS
            'VTE' => $this->processTariffChange($affiliate, $novelty),
            // Cambio de clase de riesgo ARL
            'VCT' => $this->processRiskClassChange($affiliate, $novelty),
            // Retiro (3 tipos X/P/R)
            'RET' => $this->processRetirement($affiliate, $novelty),
            // Sin efecto sobre perfil: solo se registra en afl_novelties para cálculo PILA
            // LMA, LPA, IGE, IRL, SLN, LLU, AVP, COR — RF-061 cálculo en liquidación
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
     *
     * RF-063: Retiro por mora — causal MORA_EN_APORTE siempre es retiro TOTAL.
     *   días=1, fee admin=$0, provisionar deuda (ver BillingService).
     */
    private function processRetirement(Affiliate $affiliate, Novelty $novelty): void
    {
        // RF-063: MORA_EN_APORTE fuerza retiro TOTAL independiente del scope declarado
        $scope = $this->isMoraRetirement($novelty) ? 'TOTAL' : $novelty->retirement_scope;

        match ($scope) {
            'TOTAL' => $this->statusMachine->retire($affiliate),
            'PENSION_ONLY', 'ARL_ONLY' => null, // Sigue ACTIVO
            default => null,
        };

        if ($this->requiresARLRetirementAlert($novelty)) {
            Event::dispatch(new ARLRetirementReminderRequested($novelty));
        }
    }

    /**
     * RN-28 / RF-063: ¿Requiere alerta de retiro ARL?
     * Retiro tipo X o R → plataforma ARL. Retiro por mora es siempre TOTAL efectivo.
     */
    public function requiresARLRetirementAlert(Novelty $novelty): bool
    {
        if ($novelty->novelty_type_code !== 'RET') {
            return false;
        }

        if ($this->isMoraRetirement($novelty)) {
            return true;
        }

        return in_array($novelty->retirement_scope, ['TOTAL', 'ARL_ONLY'], true);
    }

    /**
     * RF-063: ¿Es un retiro por mora?
     * Causal MORA_EN_APORTE → días=1, fee admin=$0, provisionar deuda.
     *
     * @see DOCUMENTO_RECTOR §5.2, SKILL.md RN-06, CASO 12
     */
    public function isMoraRetirement(Novelty $novelty): bool
    {
        return $novelty->novelty_type_code === 'RET'
            && $novelty->retirement_cause === 'MORA_EN_APORTE';
    }

    /**
     * RF-061: ING — Activa al afiliado si venía del estado AFILIADO (primer aporte).
     * Form_005 CASO 7: primer aporte proporcional con novedad ING automática.
     *
     * @see DOCUMENTO_RECTOR §3.4
     */
    private function processIngreso(Affiliate $affiliate): void
    {
        if ($this->statusMachine->currentStatusCode($affiliate) === 'AFILIADO') {
            $this->statusMachine->activateOnFirstPayment($affiliate);
        }
    }

    /**
     * RF-061: VTE — Variación de tarifa EPS.
     * Versiona perfil con la nueva tarifa en eps_tarifa.
     *
     * @see DOCUMENTO_RECTOR §3.4
     */
    private function processTariffChange(Affiliate $affiliate, Novelty $novelty): void
    {
        if ($novelty->new_value === null) {
            return;
        }

        $this->profileService->versionProfileForTariffChange(
            $affiliate,
            'eps_tarifa',
            (float) $novelty->new_value,
        );
    }

    /**
     * RF-061: VCT — Cambio de clase de riesgo ARL.
     * Versiona perfil con la nueva clase en arl_risk_class.
     *
     * @see DOCUMENTO_RECTOR §3.4
     */
    private function processRiskClassChange(Affiliate $affiliate, Novelty $novelty): void
    {
        if ($novelty->new_value === null) {
            return;
        }

        $this->profileService->versionProfileForRiskClassChange(
            $affiliate,
            (int) $novelty->new_value,
        );
    }

    /**
     * RF-061: Valida que el tipo de novedad no genere una combinación inválida
     * con novedades ya existentes en el mismo período.
     *
     * @throws \InvalidArgumentException si la combinación está prohibida
     *
     * @see DOCUMENTO_RECTOR §3.4 — "Combinaciones válidas"
     */
    private function assertNoCombinationConflict(
        Affiliate $affiliate,
        Periodo $period,
        string $incomingCode,
    ): void {
        $existing = $this->forPeriod($affiliate, $period)
            ->pluck('novelty_type_code')
            ->toArray();

        foreach (self::INCOMPATIBLE_PAIRS as [$a, $b]) {
            if ($incomingCode === $a && in_array($b, $existing, true)) {
                throw new \InvalidArgumentException(
                    "Combinación inválida: {$a} no puede coexistir con {$b} en el mismo período.",
                );
            }
            if ($incomingCode === $b && in_array($a, $existing, true)) {
                throw new \InvalidArgumentException(
                    "Combinación inválida: {$b} no puede coexistir con {$a} en el mismo período.",
                );
            }
        }
    }
}
