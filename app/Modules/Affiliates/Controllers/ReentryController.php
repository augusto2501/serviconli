<?php

namespace App\Modules\Affiliates\Controllers;

// RF-012, RF-013, RF-014 — búsqueda, borrador por pasos y cierre de reingreso

use App\Http\Controllers\Controller;
use App\Modules\Advisors\Models\Advisor;
use App\Modules\Affiliates\Models\Affiliate;
use App\Modules\Affiliates\Models\Person;
use App\Modules\Affiliates\Models\ReentryProcess;
use App\Modules\Affiliates\Services\PostEnrollmentCompletionService;
use App\Modules\Affiliations\Models\AffiliatePayer;
use App\Modules\Affiliations\Models\SocialSecurityProfile;
use App\Modules\Billing\Models\BillInvoice;
use App\Modules\RegulatoryEngine\Models\AffiliateStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class ReentryController extends Controller
{
    /** RF-012 — afiliado RETIRADO o INACTIVO por número de documento. */
    public function eligible(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Affiliate::class);

        $validated = $request->validate([
            'document_number' => ['required', 'string', 'max:32'],
        ]);

        $affiliate = Affiliate::query()
            ->with(['person', 'status'])
            ->join('core_people', 'core_people.id', '=', 'afl_affiliates.person_id')
            ->join('cfg_affiliate_statuses', 'cfg_affiliate_statuses.id', '=', 'afl_affiliates.status_id')
            ->where('core_people.document_number', $validated['document_number'])
            ->whereIn('cfg_affiliate_statuses.code', ['RETIRADO', 'INACTIVO'])
            ->select('afl_affiliates.*')
            ->first();

        if ($affiliate === null) {
            return response()->json([
                'message' => 'No se encontró un afiliado elegible para reingreso con ese documento.',
            ], 404);
        }

        $affiliate->loadMissing(['person', 'status']);

        return response()->json([
            'eligible' => true,
            'affiliateId' => $affiliate->id,
            'statusCode' => $affiliate->status?->code,
            'documentNumber' => $affiliate->person?->document_number,
            'firstName' => $affiliate->person?->first_name,
            'firstSurname' => $affiliate->person?->first_surname,
        ]);
    }

    /** Inicia o reutiliza un proceso DRAFT de reingreso. */
    public function start(Request $request): JsonResponse
    {
        $this->authorize('create', ReentryProcess::class);

        $validated = $request->validate([
            'affiliate_id' => ['required', 'integer', 'exists:afl_affiliates,id'],
        ]);

        $affiliate = Affiliate::query()->with('status')->findOrFail($validated['affiliate_id']);
        $code = $affiliate->status?->code;
        if (! in_array($code, ['RETIRADO', 'INACTIVO'], true)) {
            return response()->json([
                'message' => 'El afiliado no está en estado RETIRADO o INACTIVO.',
            ], 422);
        }

        $existing = ReentryProcess::query()
            ->where('affiliate_id', $affiliate->id)
            ->where('status', 'DRAFT')
            ->first();

        if ($existing !== null) {
            return response()->json($this->processToArray($existing), 200);
        }

        $process = ReentryProcess::query()->create([
            'status' => 'DRAFT',
            'current_step' => 1,
            'affiliate_id' => $affiliate->id,
        ]);

        return response()->json($this->processToArray($process), 201);
    }

    public function step1(Request $request): JsonResponse
    {
        $process = $this->draftProcessFromRequest($request);
        $this->authorize('update', $process);
        $this->ensureStepAllowed($process, 1);

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
            'step1_payload' => $validated,
            'current_step' => max($process->current_step, 2),
        ]);

        return response()->json($this->processToArray($process->fresh()));
    }

    public function step2(Request $request): JsonResponse
    {
        $process = $this->draftProcessFromRequest($request);
        $this->authorize('update', $process);
        $this->ensureStepAllowed($process, 2);
        if ($process->step1_payload === null) {
            throw ValidationException::withMessages([
                'process_id' => 'Debe completar el paso 1 antes del paso 2.',
            ]);
        }

        $validated = $request->validate([
            'eps_entity_id' => ['nullable', 'integer', 'exists:cfg_ss_entities,id'],
            'afp_entity_id' => ['nullable', 'integer', 'exists:cfg_ss_entities,id'],
            'arl_entity_id' => ['nullable', 'integer', 'exists:cfg_ss_entities,id'],
            'ccf_entity_id' => ['nullable', 'integer', 'exists:cfg_ss_entities,id'],
            'valid_from' => ['required', 'date'],
        ]);

        $process->update([
            'step2_payload' => $validated,
            'current_step' => max($process->current_step, 3),
        ]);

        return response()->json($this->processToArray($process->fresh()));
    }

    public function step3(Request $request): JsonResponse
    {
        $process = $this->draftProcessFromRequest($request);
        $this->authorize('update', $process);
        $this->ensureStepAllowed($process, 3);
        if ($process->step2_payload === null) {
            throw ValidationException::withMessages([
                'process_id' => 'Debe completar el paso 2 antes del paso 3.',
            ]);
        }

        $validated = $request->validate([
            'payer_id' => ['required', 'integer', 'exists:afl_payers,id'],
            'contributor_type_code' => ['required', 'string', 'max:16'],
            'start_date' => ['required', 'date'],
            'advisor_id' => ['nullable', 'integer', 'exists:sec_advisors,id'],
        ]);

        $process->update([
            'step3_payload' => $validated,
            'current_step' => max($process->current_step, 4),
        ]);

        return response()->json($this->processToArray($process->fresh()));
    }

    /** RF-014 — perfil SS vigente nuevo, recibo tipo 03, estado AFILIADO. */
    public function confirm(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'process_id' => ['required', 'integer', 'min:1'],
            'payment_method' => ['required', 'string', 'max:32'],
            'invoice_total_pesos' => ['nullable', 'integer', 'min:0', 'max:999999999999'],
        ]);

        $process = ReentryProcess::query()->find($validated['process_id']);
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

        if ($process->current_step < 4 || $process->step1_payload === null || $process->step2_payload === null || $process->step3_payload === null) {
            return response()->json(['message' => 'Complete los pasos 1 a 3 antes de confirmar el reingreso.'], 422);
        }

        $invoiceTotal = (int) ($validated['invoice_total_pesos'] ?? 0);

        $s3Pre = $process->step3_payload ?? [];
        if (($validated['payment_method'] ?? '') === 'CREDITO') {
            $aid = (int) ($s3Pre['advisor_id'] ?? 0);
            if ($aid < 1) {
                throw ValidationException::withMessages([
                    'payment_method' => 'CREDITO requiere asesor en el paso 3 (advisor_id).',
                ]);
            }
            $advisor = Advisor::query()->find($aid);
            if ($advisor === null || ! $advisor->authorizes_credits) {
                throw ValidationException::withMessages([
                    'payment_method' => 'El asesor debe existir y estar autorizado para ventas a crédito.',
                ]);
            }
        }

        $affiliateId = $process->affiliate_id;

        DB::transaction(function () use ($process, $validated, $invoiceTotal, $affiliateId): void {
            $affiliate = Affiliate::query()->lockForUpdate()->findOrFail($affiliateId);
            $s1 = $process->step1_payload ?? [];
            $s2 = $process->step2_payload ?? [];
            $s3 = $process->step3_payload ?? [];

            $person = Person::query()->findOrFail($affiliate->person_id);
            $person->fill([
                'document_type' => $s1['document_type'],
                'document_number' => $s1['document_number'],
                'first_name' => $s1['first_name'],
                'second_name' => $s1['second_name'] ?? null,
                'first_surname' => $s1['first_surname'],
                'second_surname' => $s1['second_surname'] ?? null,
                'gender' => $s1['gender'],
                'address' => $s1['address'],
                'phone1' => $s1['phone1'] ?? null,
                'phone2' => $s1['phone2'] ?? null,
                'cellphone' => $s1['cellphone'] ?? null,
                'email' => $s1['email'] ?? null,
                'is_foreigner' => (bool) ($s1['is_foreigner'] ?? false),
            ]);
            $person->save();

            $validFrom = Carbon::parse($s2['valid_from'])->startOfDay();
            $dayBefore = $validFrom->copy()->subDay()->toDateString();

            SocialSecurityProfile::query()
                ->where('affiliate_id', $affiliate->id)
                ->whereNull('valid_until')
                ->update(['valid_until' => $dayBefore]);

            SocialSecurityProfile::query()->create([
                'affiliate_id' => $affiliate->id,
                'eps_entity_id' => $s2['eps_entity_id'] ?? null,
                'afp_entity_id' => $s2['afp_entity_id'] ?? null,
                'arl_entity_id' => $s2['arl_entity_id'] ?? null,
                'ccf_entity_id' => $s2['ccf_entity_id'] ?? null,
                'valid_from' => $validFrom->toDateString(),
                'valid_until' => null,
            ]);

            AffiliatePayer::query()
                ->where('affiliate_id', $affiliate->id)
                ->whereNull('end_date')
                ->update(['end_date' => $dayBefore]);

            AffiliatePayer::query()->create([
                'affiliate_id' => $affiliate->id,
                'payer_id' => (int) $s3['payer_id'],
                'start_date' => $s3['start_date'],
                'end_date' => null,
                'contributor_type_code' => $s3['contributor_type_code'],
                'advisor_id' => isset($s3['advisor_id']) ? (int) $s3['advisor_id'] : null,
            ]);

            $afiliadoId = AffiliateStatus::query()->where('code', 'AFILIADO')->value('id');
            if ($afiliadoId === null) {
                throw new \RuntimeException('Falta estado AFILIADO en cfg_affiliate_statuses.');
            }

            $affiliate->update([
                'status_id' => $afiliadoId,
            ]);

            $invoice = BillInvoice::query()->create([
                'affiliate_id' => $affiliate->id,
                'payer_id' => (int) $s3['payer_id'],
                'tipo' => '03',
                'payment_method' => $validated['payment_method'],
                'total_pesos' => $invoiceTotal,
                'estado' => 'ACTIVO',
            ]);

            $process->update([
                'status' => 'COMPLETED',
                'bill_invoice_id' => $invoice->id,
                'completed_at' => now(),
            ]);
        });

        $process = $process->fresh();
        $invoice = BillInvoice::query()->findOrFail((int) $process->bill_invoice_id);
        $affiliate = Affiliate::query()->findOrFail((int) $process->affiliate_id);

        app(PostEnrollmentCompletionService::class)->handleReentry(
            $process,
            $affiliate,
            $invoice,
            $validated['payment_method'],
        );

        return response()->json([
            'processId' => $process->id,
            'status' => 'COMPLETED',
            'affiliateId' => $process->affiliate_id,
            'billInvoiceId' => $process->bill_invoice_id,
        ]);
    }

    private function draftProcessFromRequest(Request $request): ReentryProcess
    {
        $processId = (int) $request->validate([
            'process_id' => ['required', 'integer', 'min:1'],
        ])['process_id'];

        $process = ReentryProcess::query()->find($processId);
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

    private function ensureStepAllowed(ReentryProcess $process, int $step): void
    {
        $requiredPrevious = $step - 1;
        if ($process->current_step < $requiredPrevious) {
            throw ValidationException::withMessages([
                'process_id' => "No puede ejecutar paso {$step} sin completar el flujo previo.",
            ]);
        }
    }

    /** @return array<string, mixed> */
    private function processToArray(ReentryProcess $process): array
    {
        return [
            'processId' => $process->id,
            'status' => $process->status,
            'currentStep' => $process->current_step,
            'affiliateId' => $process->affiliate_id,
        ];
    }
}
