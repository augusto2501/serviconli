<?php

namespace App\Modules\PILALiquidation\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Affiliates\Models\Affiliate;
use App\Modules\PILALiquidation\Requests\StoreContributionRequest;
use App\Modules\PILALiquidation\Services\ContributionService;
use App\Modules\PILALiquidation\Strategies\PaymentMethodResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * API para Flujo 3 — Aporte Individual.
 *
 * GET  /api/contributions/prepare/{affiliate}  → datos iniciales del formulario
 * POST /api/contributions/preview               → preview de cálculo (sin guardar)
 * POST /api/contributions                       → guardar aporte
 *
 * @see DOCUMENTO_RECTOR §6 Flujo 3, RF-055..RF-060
 */
final class IndividualContributionController extends Controller
{
    /**
     * Datos iniciales para el formulario de aporte individual.
     * Devuelve: afiliado, período sugerido, días, tipo cotizante, medios de pago.
     */
    public function prepare(
        int $affiliateId,
        ContributionService $contributionService,
        PaymentMethodResolver $paymentMethodResolver,
    ): JsonResponse {
        $affiliate = Affiliate::query()
            ->with(['person', 'status', 'currentSocialSecurityProfile', 'currentAffiliatePayer'])
            ->findOrFail($affiliateId);

        $ssProfile = $affiliate->currentSocialSecurityProfile;
        $contributorTypeCode = $ssProfile?->contributor_type_code ?? '01';
        $arlRiskClass = $ssProfile?->arl_risk_class ?? 1;
        $salaryPesos = $ssProfile?->salary_pesos ?? 0;

        try {
            $periodData = $contributionService->determinePeriod($affiliate, $contributorTypeCode);
        } catch (\Throwable $e) {
            $periodData = [
                'period' => new \App\Modules\RegulatoryEngine\ValueObjects\Periodo(now()->year, now()->month),
                'days' => 30,
                'is_first_contribution' => true,
                'is_advance_period' => false,
                'novelty_ing' => true,
                'enrollment_date' => null,
            ];
        }

        $period = $periodData['period'];

        return response()->json([
            'affiliate' => [
                'id' => $affiliate->id,
                'document_number' => $affiliate->person?->document_number,
                'full_name' => trim(
                    ($affiliate->person?->first_name ?? '') . ' ' .
                    ($affiliate->person?->second_name ?? '') . ' ' .
                    ($affiliate->person?->first_surname ?? '') . ' ' .
                    ($affiliate->person?->second_surname ?? '')
                ),
                'status_code' => $affiliate->status?->code ?? 'AFILIADO',
                'mora_status' => $affiliate->mora_status,
                'is_type_51' => $affiliate->is_type_51,
            ],
            'suggested_period' => [
                'year' => $period->year,
                'month' => $period->month,
                'label' => $period->format(),
            ],
            'suggested_days' => $periodData['days'],
            'is_first_contribution' => $periodData['is_first_contribution'],
            'is_advance_period' => $periodData['is_advance_period'],
            'novelty_ing_required' => $periodData['novelty_ing'],
            'contributor_type_code' => $contributorTypeCode,
            'arl_risk_class' => $arlRiskClass,
            'salary_pesos' => $salaryPesos,
            'ss_profile' => $ssProfile !== null ? [
                'eps_entity_id' => $ssProfile->eps_entity_id,
                'afp_entity_id' => $ssProfile->afp_entity_id,
                'arl_entity_id' => $ssProfile->arl_entity_id,
                'ccf_entity_id' => $ssProfile->ccf_entity_id,
            ] : null,
            'payment_methods' => $paymentMethodResolver->available(),
        ]);
    }

    /**
     * Preview de cálculo en tiempo real — NO guarda nada.
     * El frontend llama a esto cada vez que cambia un campo relevante.
     */
    public function preview(Request $request, ContributionService $contributionService): JsonResponse
    {
        $validated = $request->validate([
            'affiliate_id' => ['required', 'integer'],
            'period_year' => ['required', 'integer'],
            'period_month' => ['required', 'integer'],
            'salary_pesos' => ['required', 'integer', 'min:1'],
            'days_eps' => ['required', 'integer', 'min:1', 'max:30'],
            'days_afp' => ['nullable', 'integer', 'min:0', 'max:30'],
            'days_arl' => ['nullable', 'integer', 'min:0', 'max:30'],
            'days_ccf' => ['nullable', 'integer', 'min:0', 'max:30'],
            'contributor_type_code' => ['required', 'string'],
            'subtipo' => ['nullable', 'integer'],
            'arl_risk_class' => ['required', 'integer', 'min:1', 'max:5'],
            'admin_fee_pesos' => ['nullable', 'integer', 'min:0'],
        ]);

        try {
            $result = $contributionService->preview($validated);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Error en cálculo: ' . $e->getMessage()], 500);
        }

        return response()->json([
            'ibc_rounded_pesos' => $result->ibcRoundedPesos,
            'subsystems' => $result->subsystemAmountsPesos,
            'total_pesos' => $result->totalSocialSecurityPesos,
        ]);
    }

    /**
     * Guarda el aporte individual — Flujo 3 completo.
     */
    public function store(
        StoreContributionRequest $request,
        ContributionService $contributionService,
        PaymentMethodResolver $paymentMethodResolver,
    ): JsonResponse {
        $validated = $request->validated();

        try {
            $storeResult = $contributionService->store($validated, $request->user()?->id ?? 0);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $liquidation = $storeResult['liquidation'];

        $paymentResult = $paymentMethodResolver
            ->resolve($validated['payment_method'])
            ->process($liquidation, $this->extractPaymentContext($validated));

        $liquidation->load('lines');

        return response()->json([
            'liquidation' => [
                'id' => $liquidation->id,
                'public_id' => $liquidation->public_id,
                'status' => $liquidation->status->value,
                'total_pesos' => $liquidation->total_social_security_pesos,
                'period' => [
                    'year' => $liquidation->lines->first()?->period_year,
                    'month' => $liquidation->lines->first()?->period_month,
                ],
                'subsystems' => $storeResult['result']->subsystemAmountsPesos,
            ],
            'payment' => $paymentResult,
            'alerts' => $storeResult['alerts'],
        ], 201);
    }

    private function extractPaymentContext(array $data): array
    {
        return array_filter([
            'bank_name' => $data['bank_name'] ?? null,
            'bank_reference' => $data['bank_reference'] ?? null,
            'bank_amount' => $data['bank_amount'] ?? null,
            'bank_deposit_type' => $data['bank_deposit_type'] ?? null,
            'payer_id' => $data['payer_id'] ?? null,
        ]);
    }
}
