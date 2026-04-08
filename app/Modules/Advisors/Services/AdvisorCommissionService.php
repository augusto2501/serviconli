<?php

namespace App\Modules\Advisors\Services;

use App\Modules\Advisors\Models\Advisor;
use App\Modules\Advisors\Models\AdvisorCommission;
use App\Modules\Affiliates\Models\Affiliate;
use App\Modules\Affiliates\Models\EnrollmentProcess;
use App\Modules\Affiliates\Models\ReentryProcess;
use App\Modules\Billing\Models\BillInvoice;
use App\Modules\Billing\Services\ConsecutiveService;
use App\Modules\ThirdParties\Models\AdvisorReceivable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

final class AdvisorCommissionService
{
    public function __construct(
        private readonly ConsecutiveService $consecutiveService,
    ) {}

    public function recordNewAffiliation(EnrollmentProcess $process, Affiliate $affiliate): ?AdvisorCommission
    {
        $step5 = $process->step5_payload ?? [];
        $advisorId = isset($step5['advisor_id']) ? (int) $step5['advisor_id'] : null;
        if ($advisorId === null || $advisorId < 1) {
            return null;
        }

        $advisor = Advisor::query()->find($advisorId);
        if ($advisor === null || (int) $advisor->commission_new <= 0) {
            return null;
        }

        if (AdvisorCommission::query()->where('enrollment_process_id', $process->id)->exists()) {
            return AdvisorCommission::query()->where('enrollment_process_id', $process->id)->first();
        }

        return $this->createCommission(
            advisor: $advisor,
            affiliateId: $affiliate->id,
            enrollmentProcessId: $process->id,
            reentryProcessId: null,
            type: 'NEW',
            amountPesos: (int) $advisor->commission_new,
        );
    }

    /**
     * @return array{commission: ?AdvisorCommission, receivable: ?AdvisorReceivable}
     */
    public function recordReentry(
        ReentryProcess $process,
        Affiliate $affiliate,
        BillInvoice $invoice,
        string $paymentMethod,
    ): array {
        $s3 = $process->step3_payload ?? [];
        $advisorId = isset($s3['advisor_id']) ? (int) $s3['advisor_id'] : null;

        $commission = null;
        $receivable = null;

        $advisor = ($advisorId !== null && $advisorId > 0) ? Advisor::query()->find($advisorId) : null;

        if ($advisor !== null) {
            if ((int) $advisor->commission_recurring > 0
                && ! AdvisorCommission::query()->where('reentry_process_id', $process->id)->exists()) {
                $commission = $this->createCommission(
                    advisor: $advisor,
                    affiliateId: $affiliate->id,
                    enrollmentProcessId: null,
                    reentryProcessId: $process->id,
                    type: 'RECURRING',
                    amountPesos: (int) $advisor->commission_recurring,
                );
            }

            if ($paymentMethod === 'CREDITO' && $advisor->authorizes_credits
                && (int) $invoice->total_pesos > 0) {
                $receivable = AdvisorReceivable::query()->firstOrCreate(
                    ['bill_invoice_id' => $invoice->id],
                    [
                        'advisor_id' => $advisor->id,
                        'amount_pesos' => (int) $invoice->total_pesos,
                        'status' => 'PENDIENTE',
                        'created_by' => Auth::id(),
                    ],
                );
            }
        }

        return ['commission' => $commission, 'receivable' => $receivable];
    }

    private function createCommission(
        Advisor $advisor,
        int $affiliateId,
        ?int $enrollmentProcessId,
        ?int $reentryProcessId,
        string $type,
        int $amountPesos,
    ): AdvisorCommission {
        return DB::transaction(function () use ($advisor, $affiliateId, $enrollmentProcessId, $reentryProcessId, $type, $amountPesos): AdvisorCommission {
            $publicNumber = $this->consecutiveService->next('CE');

            return AdvisorCommission::query()->create([
                'public_number' => $publicNumber,
                'advisor_id' => $advisor->id,
                'affiliate_id' => $affiliateId,
                'enrollment_process_id' => $enrollmentProcessId,
                'reentry_process_id' => $reentryProcessId,
                'commission_type' => $type,
                'amount_pesos' => $amountPesos,
                'status' => 'CALCULADA',
                'created_by' => Auth::id(),
            ]);
        });
    }
}
