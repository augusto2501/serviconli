<?php

namespace App\Modules\Affiliates\Controllers;

// RF-001, RF-005, RF-006 — wizard backend por pasos con validación y cierre.

use App\Http\Controllers\Controller;
use App\Modules\Affiliates\Enums\AffiliateClientType;
use App\Modules\Affiliates\Models\Affiliate;
use App\Modules\Affiliates\Models\Beneficiary;
use App\Modules\Affiliates\Models\EnrollmentProcess;
use App\Modules\Affiliates\Models\GdprConsentRecord;
use App\Modules\Affiliates\Models\Person;
use App\Modules\Advisors\Models\Advisor;
use App\Modules\Affiliates\Services\EnrollmentBillingPreviewService;
use App\Modules\Affiliates\Services\PostEnrollmentCompletionService;
use App\Modules\Affiliates\Services\RadicadoNumberGenerator;
use App\Modules\Affiliations\Models\SocialSecurityProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class EnrollmentController extends Controller
{
    public function step1(Request $request): JsonResponse
    {
        $this->authorize('create', EnrollmentProcess::class);

        $validated = $request->validate([
            'client_type' => ['required', 'string', Rule::enum(AffiliateClientType::class)],
            'contributor_type_code' => ['required', 'string', 'max:16'],
            'subtipo' => ['nullable', 'integer', 'min:0', 'max:99'],
            'is_type_51' => ['nullable', 'boolean'],
        ]);

        $process = EnrollmentProcess::query()->create([
            'status' => 'DRAFT',
            'current_step' => 1,
            'step1_payload' => $validated,
        ]);

        return response()->json([
            'processId' => $process->id,
            'status' => $process->status,
            'currentStep' => $process->current_step,
        ], 201);
    }

    public function step2(Request $request): JsonResponse
    {
        $process = $this->draftProcessFromRequest($request);
        $this->authorize('update', $process);
        $this->ensureStepAllowed($process, 2);

        $validated = $request->validate([
            'document_type' => ['required', 'string', Rule::in(['CC', 'CE', 'TI', 'PA', 'PPT', 'PTT', 'NIT'])],
            'document_number' => ['required', 'string', 'max:32'],
            'first_name' => ['required', 'string', 'max:255'],
            'first_surname' => ['required', 'string', 'max:255'],
            'gender' => ['required', 'string', 'max:32'],
            'address' => ['required', 'string', 'max:255'],
            'phone1' => ['nullable', 'string', 'max:32', 'required_without_all:phone2,cellphone'],
            'phone2' => ['nullable', 'string', 'max:32', 'required_without_all:phone1,cellphone'],
            'cellphone' => ['nullable', 'string', 'max:32', 'required_without_all:phone1,phone2'],
            'second_name' => ['nullable', 'string', 'max:255'],
            'second_surname' => ['nullable', 'string', 'max:255'],
            'is_foreigner' => ['nullable', 'boolean'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        $process->update([
            'step2_payload' => $validated,
            'current_step' => max($process->current_step, 2),
        ]);

        return response()->json($this->processToArray($process->fresh()));
    }

    public function step3(Request $request): JsonResponse
    {
        $process = $this->draftProcessFromRequest($request);
        $this->authorize('update', $process);
        $this->ensureStepAllowed($process, 3);

        $validated = $request->validate([
            'beneficiaries' => ['required', 'array'],
            'beneficiaries.*.document_number' => ['required', 'string', 'max:32'],
            'beneficiaries.*.document_type' => ['nullable', 'string', 'max:16'],
            'beneficiaries.*.first_name' => ['nullable', 'string', 'max:255'],
            'beneficiaries.*.surnames' => ['nullable', 'string', 'max:255'],
            'beneficiaries.*.gender' => ['nullable', 'string', 'max:16'],
            'beneficiaries.*.parentesco' => ['nullable', 'string', 'max:64'],
            'beneficiaries.*.birth_date' => ['nullable', 'date'],
        ]);

        $process->update([
            'step3_payload' => $validated,
            'current_step' => max($process->current_step, 3),
        ]);

        return response()->json($this->processToArray($process->fresh()));
    }

    public function step4(Request $request): JsonResponse
    {
        $process = $this->draftProcessFromRequest($request);
        $this->authorize('update', $process);
        $this->ensureStepAllowed($process, 4);

        $validated = $request->validate([
            'eps_entity_id' => ['nullable', 'integer', 'exists:cfg_ss_entities,id'],
            'afp_entity_id' => ['nullable', 'integer', 'exists:cfg_ss_entities,id'],
            'arl_entity_id' => ['nullable', 'integer', 'exists:cfg_ss_entities,id'],
            'ccf_entity_id' => ['nullable', 'integer', 'exists:cfg_ss_entities,id'],
            'operator_code' => ['nullable', 'string', 'max:32'],
            'valid_from' => ['nullable', 'date'],
        ]);

        $process->update([
            'step4_payload' => $validated,
            'current_step' => max($process->current_step, 4),
        ]);

        return response()->json($this->processToArray($process->fresh()));
    }

    public function step5(Request $request): JsonResponse
    {
        $process = $this->draftProcessFromRequest($request);
        $this->authorize('update', $process);
        $this->ensureStepAllowed($process, 5);

        $validated = $request->validate([
            'payment_method' => ['required', 'string', 'max:32'],
            'billing_mode' => ['nullable', 'string', 'max:32'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'raw_ibc_pesos' => ['required', 'integer', 'min:1', 'max:999999999999'],
            'arl_risk_class' => ['nullable', 'integer', 'min:1', 'max:5'],
            'advisor_id' => ['nullable', 'integer', 'exists:sec_advisors,id'],
        ]);

        if (($validated['payment_method'] ?? '') === 'CREDITO') {
            $aid = isset($validated['advisor_id']) ? (int) $validated['advisor_id'] : 0;
            if ($aid < 1) {
                throw ValidationException::withMessages([
                    'advisor_id' => 'El medio CREDITO requiere un asesor autorizado.',
                ]);
            }
            $advisor = Advisor::query()->find($aid);
            if ($advisor === null || ! $advisor->authorizes_credits) {
                throw ValidationException::withMessages([
                    'advisor_id' => 'El asesor debe existir y estar autorizado para ventas a crédito.',
                ]);
            }
        }

        $arlRisk = (int) ($validated['arl_risk_class'] ?? 1);
        $preview = app(EnrollmentBillingPreviewService::class)->preview(
            $process,
            (int) $validated['raw_ibc_pesos'],
            $arlRisk,
        );

        $step5Payload = array_merge($validated, ['billing_preview' => $preview]);

        $process->update([
            'step5_payload' => $step5Payload,
            'current_step' => max($process->current_step, 5),
        ]);

        $fresh = $process->fresh();

        return response()->json(array_merge($this->processToArray($fresh), [
            'billingPreview' => $preview,
        ]));
    }

    public function confirm(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'process_id' => ['required', 'integer', 'min:1'],
            'habeas_data_accepted' => ['required', 'boolean'],
        ]);

        if (! $validated['habeas_data_accepted']) {
            throw ValidationException::withMessages([
                'habeas_data_accepted' => 'Debe aceptar el tratamiento de datos personales (Ley 1581/2012).',
            ]);
        }

        $process = EnrollmentProcess::query()->find($validated['process_id']);
        if ($process === null) {
            throw ValidationException::withMessages([
                'process_id' => 'Proceso no encontrado.',
            ]);
        }
        if ($process->status !== 'DRAFT') {
            throw ValidationException::withMessages([
                'process_id' => 'Proceso ya finalizado.',
            ]);
        }

        $this->authorize('update', $process);

        if ($process->current_step < 5 || $process->step1_payload === null || $process->step2_payload === null || $process->step3_payload === null || $process->step4_payload === null || $process->step5_payload === null) {
            return response()->json(['message' => 'No puede confirmar: faltan pasos previos del wizard.'], 422);
        }

        $radicadoGenerator = app(RadicadoNumberGenerator::class);

        $affiliate = DB::transaction(function () use ($process, $request, $radicadoGenerator): Affiliate {
            $radicado = $radicadoGenerator->next();

            $acceptedAt = now();
            $s1 = $process->step1_payload ?? [];
            $s2 = $process->step2_payload ?? [];
            $s3 = $process->step3_payload ?? [];
            $s4 = $process->step4_payload ?? [];

            $person = Person::query()->create([
                'document_type' => $s2['document_type'] ?? null,
                'document_number' => $s2['document_number'],
                'first_name' => $s2['first_name'],
                'second_name' => $s2['second_name'] ?? null,
                'first_surname' => $s2['first_surname'],
                'second_surname' => $s2['second_surname'] ?? null,
                'gender' => $s2['gender'],
                'address' => $s2['address'],
                'phone1' => $s2['phone1'] ?? null,
                'phone2' => $s2['phone2'] ?? null,
                'cellphone' => $s2['cellphone'] ?? null,
                'email' => $s2['email'] ?? null,
                'is_foreigner' => (bool) ($s2['is_foreigner'] ?? false),
            ]);

            $affiliate = Affiliate::query()->create([
                'person_id' => $person->id,
                'client_type' => AffiliateClientType::from($s1['client_type']),
                'subtipo' => $s1['subtipo'] ?? null,
                'is_type_51' => (bool) ($s1['is_type_51'] ?? false),
            ]);

            if (($s3['beneficiaries'] ?? []) !== []) {
                foreach ($s3['beneficiaries'] as $b) {
                    Beneficiary::query()->create([
                        'affiliate_id' => $affiliate->id,
                        'document_type' => $b['document_type'] ?? null,
                        'document_number' => $b['document_number'],
                        'first_name' => $b['first_name'] ?? null,
                        'surnames' => $b['surnames'] ?? null,
                        'gender' => $b['gender'] ?? null,
                        'parentesco' => $b['parentesco'] ?? null,
                        'birth_date' => $b['birth_date'] ?? null,
                    ]);
                }
            }

            SocialSecurityProfile::query()->create([
                'affiliate_id' => $affiliate->id,
                'eps_entity_id' => $s4['eps_entity_id'] ?? null,
                'afp_entity_id' => $s4['afp_entity_id'] ?? null,
                'arl_entity_id' => $s4['arl_entity_id'] ?? null,
                'ccf_entity_id' => $s4['ccf_entity_id'] ?? null,
                'valid_from' => $s4['valid_from'] ?? now()->toDateString(),
                'valid_until' => null,
            ]);

            $process->update([
                'status' => 'COMPLETED',
                'current_step' => 6,
                'affiliate_id' => $affiliate->id,
                'radicado_number' => $radicado,
                'completed_at' => $acceptedAt,
            ]);

            GdprConsentRecord::query()->create([
                'enrollment_process_id' => $process->id,
                'affiliate_id' => $affiliate->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'accepted_at' => $acceptedAt,
            ]);

            return $affiliate;
        });

        $process = $process->fresh();
        app(PostEnrollmentCompletionService::class)->handle($process, $affiliate);

        return response()->json([
            'processId' => $process->id,
            'status' => 'COMPLETED',
            'affiliateId' => $affiliate->id,
            'radicadoNumber' => $process->radicado_number,
        ]);
    }

    private function draftProcessFromRequest(Request $request): EnrollmentProcess
    {
        $processId = (int) $request->validate([
            'process_id' => ['required', 'integer', 'min:1'],
        ])['process_id'];

        $process = EnrollmentProcess::query()->find($processId);
        if ($process === null) {
            throw ValidationException::withMessages([
                'process_id' => 'Proceso no encontrado.',
            ]);
        }
        if ($process->status !== 'DRAFT') {
            throw ValidationException::withMessages([
                'process_id' => 'Proceso ya finalizado.',
            ]);
        }

        return $process;
    }

    private function ensureStepAllowed(EnrollmentProcess $process, int $step): void
    {
        $requiredPrevious = $step - 1;
        if ($process->current_step < $requiredPrevious) {
            throw ValidationException::withMessages([
                'process_id' => "No puede ejecutar paso {$step} sin completar paso {$requiredPrevious}.",
            ]);
        }
    }

    /** @return array<string, mixed> */
    private function processToArray(EnrollmentProcess $process): array
    {
        return [
            'processId' => $process->id,
            'status' => $process->status,
            'currentStep' => $process->current_step,
        ];
    }
}
