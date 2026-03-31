<?php

namespace App\Modules\PILALiquidation\Services;

use App\Modules\Affiliates\Models\Affiliate;
use App\Modules\Affiliates\Models\Novelty;
use App\Modules\PILALiquidation\Enums\PilaLiquidationStatus;
use App\Modules\PILALiquidation\Events\ContributionSaved;
use App\Modules\PILALiquidation\Models\PilaLiquidation;
use App\Modules\PILALiquidation\Models\PilaLiquidationLine;
use App\Modules\RegulatoryEngine\DTOs\CalculationContext;
use App\Modules\RegulatoryEngine\DTOs\CalculationResultDTO;
use App\Modules\RegulatoryEngine\Services\PILACalculationService;
use App\Modules\RegulatoryEngine\Services\PeriodDeterminationService;
use App\Modules\RegulatoryEngine\Strategies\StrategyResolver;
use App\Modules\RegulatoryEngine\ValueObjects\Periodo;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Orquestador del Flujo 3 — Aporte Individual.
 *
 * Portado de Access Form_005 (1715 líneas VBA).
 *
 * Flujo: determinar período → verificar días [RN-25,RN-27] → novedades [RN-06]
 *   → calcular 11 pasos → verificar mora [RN-11,RN-13] → guardar
 *   → recibo [RN-12] → actualizar estado [RN-05] → procesar novedades perfil
 *   → alerta ARL [RN-28].
 *
 * @see DOCUMENTO_RECTOR §6 Flujo 3, RF-055..RF-060
 */
final class ContributionService
{
    public function __construct(
        private readonly PILACalculationService $calculationService,
        private readonly PeriodDeterminationService $periodService,
        private readonly StrategyResolver $strategyResolver,
    ) {}

    /**
     * Preview de cálculo (sin guardar) — para el formulario en tiempo real.
     */
    public function preview(array $data): CalculationResultDTO
    {
        $context = $this->buildContext($data);

        return $this->calculationService->calculateFull(
            $context,
            'AFFILIATE',
            (int) $data['affiliate_id'],
            (int) ($data['days_late'] ?? 0),
        );
    }

    /**
     * Guarda el aporte individual y dispara eventos post-guardado.
     *
     * @return array{liquidation: PilaLiquidation, result: CalculationResultDTO, alerts: list<string>}
     */
    public function store(array $data, int $userId): array
    {
        $affiliateId = (int) $data['affiliate_id'];
        $affiliate = Affiliate::query()->with(['status', 'currentAffiliatePayer'])->findOrFail($affiliateId);

        $this->validateBusinessRules($data, $affiliate);

        $context = $this->buildContext($data);
        $daysLate = (int) ($data['days_late'] ?? 0);
        $result = $this->calculationService->calculateFull($context, 'AFFILIATE', $affiliateId, $daysLate);

        $period = new Periodo((int) $data['period_year'], (int) $data['period_month']);

        $liquidation = PilaLiquidation::query()->create([
            'public_id' => Str::uuid()->toString(),
            'status' => PilaLiquidationStatus::Confirmed,
            'contributor_type_code' => $data['contributor_type_code'],
            'arl_risk_class' => (int) $data['arl_risk_class'],
            'payment_date' => now()->toDateString(),
            'document_last_two_digits' => 0,
            'affiliate_id' => $affiliateId,
            'total_social_security_pesos' => $result->totalSocialSecurityPesos,
            'subsystem_totals_pesos' => $result->subsystemAmountsPesos,
        ]);

        PilaLiquidationLine::query()->create([
            'pila_liquidation_id' => $liquidation->id,
            'line_number' => 1,
            'period_year' => $period->year,
            'period_month' => $period->month,
            'raw_ibc_pesos' => (int) $data['salary_pesos'],
            'ibc_rounded_pesos' => $result->ibcRoundedPesos,
            'days_late' => $daysLate,
            'payment_deadline_date' => now()->toDateString(),
            'subsystem_amounts_pesos' => $result->subsystemAmountsPesos,
            'total_social_security_pesos' => $result->totalSocialSecurityPesos,
        ]);

        $liquidation->load('lines');

        $alerts = $this->collectAlerts($data);

        ContributionSaved::dispatch(
            $affiliate,
            $liquidation,
            $data['payment_method'],
            $data['novelties'] ?? [],
            $this->extractBankData($data),
        );

        return [
            'liquidation' => $liquidation,
            'result' => $result,
            'alerts' => $alerts,
        ];
    }

    /**
     * Determina automáticamente el período para un afiliado.
     */
    public function determinePeriod(Affiliate $affiliate, string $contributorTypeCode): array
    {
        return $this->periodService->determine($affiliate, $contributorTypeCode);
    }

    /**
     * RN-25: Días < 30 sin novedad = error (excepto tipo 41).
     * RN-27: Período duplicado = error.
     */
    private function validateBusinessRules(array $data, Affiliate $affiliate): void
    {
        $daysEps = (int) $data['days_eps'];
        $novelties = $data['novelties'] ?? [];
        $contributorType = $data['contributor_type_code'];

        // RN-25: Días < 30 requiere novedad ING o RET
        if ($daysEps < 30 && $contributorType !== '41') {
            $hasRequiredNovelty = collect($novelties)->contains(
                fn ($n) => in_array($n['type_code'] ?? '', ['ING', 'RET'], true)
            );

            if (! $hasRequiredNovelty) {
                throw new InvalidArgumentException(
                    'Días menores a 30 requieren novedad de Ingreso (ING) o Retiro (RET). '
                    .'Verifique los días de cotización. [RN-25, RF-056]'
                );
            }
        }

        // RN-27: Período ya pagado = error
        $periodYear = (int) $data['period_year'];
        $periodMonth = (int) $data['period_month'];

        $exists = PilaLiquidation::query()
            ->where('affiliate_id', $affiliate->id)
            ->whereIn('status', [PilaLiquidationStatus::Confirmed->value, 'LIQUIDADO', 'PAGADO'])
            ->whereHas('lines', function ($q) use ($periodYear, $periodMonth) {
                $q->where('period_year', $periodYear)->where('period_month', $periodMonth);
            })
            ->exists();

        if ($exists) {
            throw new InvalidArgumentException(
                "Este período ({$periodYear}-{$periodMonth}) ya fue cancelado, debe verificar. [RN-27, RF-057]"
            );
        }

        // RF-058: Tipo 51 → días válidos: 7, 14, 21, 30
        if ($contributorType === '51' && ! in_array($daysEps, [7, 14, 21, 30], true)) {
            throw new InvalidArgumentException(
                'Para tipo 51 (tiempo parcial), los días válidos son: 7, 14, 21 o 30. [RF-058]'
            );
        }
    }

    private function buildContext(array $data): CalculationContext
    {
        $daysEps = (int) $data['days_eps'];
        $daysAfp = (int) ($data['days_afp'] ?? $daysEps);
        $daysArl = (int) ($data['days_arl'] ?? $daysEps);
        $daysCcf = (int) ($data['days_ccf'] ?? $daysEps);
        $period = new Periodo((int) $data['period_year'], (int) $data['period_month']);
        $date = sprintf('%04d-%02d-01', $period->year, $period->month);

        return new CalculationContext(
            salaryPesos: (int) $data['salary_pesos'],
            daysEps: $daysEps,
            daysAfp: $daysAfp,
            daysArl: $daysArl,
            daysCcf: $daysCcf,
            cotizationPeriod: $period,
            contributorTypeCode: $data['contributor_type_code'],
            subtipo: (int) ($data['subtipo'] ?? 0),
            arlRiskClass: (int) $data['arl_risk_class'],
            isType51: $data['contributor_type_code'] === '51',
            adminFeePesos: (int) ($data['admin_fee_pesos'] ?? 0),
            referenceDate: $date,
        );
    }

    /** RN-28: Alertas para retiro ARL tipo X o R. */
    private function collectAlerts(array $data): array
    {
        $alerts = [];

        foreach ($data['novelties'] ?? [] as $novelty) {
            $code = $novelty['type_code'] ?? '';
            $scope = $novelty['retirement_scope'] ?? '';

            if ($code === 'RET' && in_array($scope, ['TOTAL', 'ARL_ONLY'], true)) {
                $alerts[] = 'Recuerde retirar al afiliado en la plataforma de la ARL. [RN-28, RF-064]';
            }
        }

        return $alerts;
    }

    private function extractBankData(array $data): array
    {
        if (($data['payment_method'] ?? '') !== 'CONSIGNACION') {
            return [];
        }

        return [
            'bank_name' => $data['bank_name'] ?? null,
            'bank_reference' => $data['bank_reference'] ?? null,
            'bank_amount' => $data['bank_amount'] ?? null,
            'bank_deposit_type' => $data['bank_deposit_type'] ?? 'LOCAL',
        ];
    }
}
