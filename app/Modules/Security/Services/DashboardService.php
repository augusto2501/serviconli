<?php

namespace App\Modules\Security\Services;

use App\Modules\Affiliates\Models\Affiliate;
use App\Modules\Affiliates\Models\Beneficiary;
use App\Modules\Affiliates\Models\EnrollmentProcess;
use App\Modules\Affiliations\Models\AffiliatePayer;
use App\Modules\Billing\Models\PaymentReceived;
use App\Modules\PILALiquidation\Enums\PilaLiquidationStatus;
use App\Modules\PILALiquidation\Models\PILAFileGeneration;
use App\Modules\PILALiquidation\Models\PilaLiquidation;
use App\Modules\RegulatoryEngine\Models\AffiliateStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Dashboard gerencial — RF-114.
 *
 * Indicadores: afiliados activos/mora/inactivos, recaudo mes,
 * planillas generadas, afiliaciones nuevas, distribución tipo/operador,
 * panel alertas (mora >90, beneficiarios por vencer, períodos sin pagar).
 *
 * @see DOCUMENTO_RECTOR §15.1
 */
final class DashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function build(?Carbon $referenceDate = null): array
    {
        $now = $referenceDate ?? Carbon::now();
        $currentMonth = $now->copy()->startOfMonth();
        $previousMonth = $now->copy()->subMonth()->startOfMonth();

        return [
            'affiliates' => $this->affiliateSummary(),
            'revenue' => $this->revenueSummary($currentMonth, $previousMonth),
            'pila' => $this->pilaSummary($currentMonth),
            'enrollments' => $this->enrollmentSummary($currentMonth, $previousMonth),
            'distribution' => $this->distributionSummary(),
            'alerts' => $this->alertsSummary($now),
            'generated_at' => $now->toIso8601String(),
        ];
    }

    /**
     * RF-114: afiliados activos vs inactivos vs en mora.
     *
     * @return array<string, int>
     */
    private function affiliateSummary(): array
    {
        $statusCodes = AffiliateStatus::query()->pluck('id', 'code');

        $counts = Affiliate::query()
            ->whereNull('deleted_at')
            ->select('status_id', DB::raw('COUNT(*) as total'))
            ->groupBy('status_id')
            ->pluck('total', 'status_id');

        $codeToId = $statusCodes->flip();

        $active = 0;
        $mora = 0;
        $inactive = 0;
        $total = 0;

        foreach ($counts as $statusId => $count) {
            $code = $codeToId[$statusId] ?? '';
            $total += $count;

            if (in_array($code, ['ACTIVO', 'AFILIADO', 'PAGO_MES_SUBSIGUIENTE'], true)) {
                $active += $count;
            } elseif (str_starts_with($code, 'MORA_') || $code === 'SUSPENDIDO') {
                $mora += $count;
            } else {
                $inactive += $count;
            }
        }

        return [
            'total' => $total,
            'active' => $active,
            'mora' => $mora,
            'inactive' => $inactive,
        ];
    }

    /**
     * RF-114: recaudo mes actual vs mes anterior.
     *
     * @return array<string, int|float>
     */
    private function revenueSummary(Carbon $currentMonth, Carbon $previousMonth): array
    {
        $current = $this->revenueForMonth($currentMonth);
        $previous = $this->revenueForMonth($previousMonth);

        $variationPercent = $previous > 0
            ? round((($current - $previous) / $previous) * 100, 1)
            : 0.0;

        return [
            'current_month_pesos' => $current,
            'previous_month_pesos' => $previous,
            'variation_percent' => $variationPercent,
        ];
    }

    private function revenueForMonth(Carbon $month): int
    {
        return (int) PaymentReceived::query()
            ->where('status', 'APLICADO')
            ->whereBetween('payment_date', [
                $month->copy()->startOfMonth()->toDateString(),
                $month->copy()->endOfMonth()->toDateString(),
            ])
            ->sum('amount_pesos');
    }

    /**
     * RF-114: planillas generadas del período.
     *
     * @return array<string, int>
     */
    private function pilaSummary(Carbon $currentMonth): array
    {
        $year = $currentMonth->year;
        $month = $currentMonth->month;

        $liquidationsCount = PilaLiquidation::query()
            ->whereHas('lines', function ($q) use ($year, $month): void {
                $q->where('period_year', $year)->where('period_month', $month);
            })
            ->count();

        $confirmedCount = PilaLiquidation::query()
            ->where('status', PilaLiquidationStatus::Confirmed)
            ->whereHas('lines', function ($q) use ($year, $month): void {
                $q->where('period_year', $year)->where('period_month', $month);
            })
            ->count();

        $filesGenerated = PILAFileGeneration::query()
            ->where('period_year', $year)
            ->where('period_month', $month)
            ->count();

        return [
            'liquidations_total' => $liquidationsCount,
            'liquidations_confirmed' => $confirmedCount,
            'files_generated' => $filesGenerated,
        ];
    }

    /**
     * RF-114: afiliaciones nuevas mes actual vs anterior.
     *
     * @return array<string, int>
     */
    private function enrollmentSummary(Carbon $currentMonth, Carbon $previousMonth): array
    {
        $current = (int) EnrollmentProcess::query()
            ->where('status', 'COMPLETADO')
            ->whereBetween('completed_at', [
                $currentMonth->copy()->startOfMonth(),
                $currentMonth->copy()->endOfMonth()->endOfDay(),
            ])
            ->count();

        $previous = (int) EnrollmentProcess::query()
            ->where('status', 'COMPLETADO')
            ->whereBetween('completed_at', [
                $previousMonth->copy()->startOfMonth(),
                $previousMonth->copy()->endOfMonth()->endOfDay(),
            ])
            ->count();

        return [
            'current_month' => $current,
            'previous_month' => $previous,
        ];
    }

    /**
     * RF-114: distribución por tipo de cliente y por operador PILA.
     *
     * @return array<string, array<string, int>>
     */
    private function distributionSummary(): array
    {
        $byClientType = Affiliate::query()
            ->whereNull('deleted_at')
            ->select('client_type', DB::raw('COUNT(*) as total'))
            ->groupBy('client_type')
            ->pluck('total', 'client_type')
            ->toArray();

        // RF-114: distribución por operador PILA (from payer's pila_operator_code)
        $byOperator = AffiliatePayer::query()
            ->whereNull('end_date')
            ->join('afl_payers', 'afl_payers.id', '=', 'afl_affiliate_payer.payer_id')
            ->whereNotNull('afl_payers.pila_operator_code')
            ->select('afl_payers.pila_operator_code', DB::raw('COUNT(*) as total'))
            ->groupBy('afl_payers.pila_operator_code')
            ->pluck('total', 'pila_operator_code')
            ->toArray();

        return [
            'by_client_type' => $byClientType,
            'by_pila_operator' => $byOperator,
        ];
    }

    /**
     * RF-114: panel de alertas.
     *
     * @return array<string, int>
     */
    private function alertsSummary(Carbon $now): array
    {
        $moraCodes = ['MORA_90', 'MORA_120', 'MORA_120_PLUS'];
        $moraStatusIds = AffiliateStatus::query()
            ->whereIn('code', $moraCodes)
            ->pluck('id');

        $moraOver90 = Affiliate::query()
            ->whereNull('deleted_at')
            ->whereIn('status_id', $moraStatusIds)
            ->count();

        // Beneficiarios próximos a cumplir 18 años (30 días)
        $turning18 = Beneficiary::query()
            ->whereNotNull('birth_date')
            ->whereBetween('birth_date', [
                $now->copy()->subYears(18)->toDateString(),
                $now->copy()->subYears(18)->addDays(30)->toDateString(),
            ])
            ->count();

        // Certificados de estudiante por vencer (30 días)
        $certExpiring = Beneficiary::query()
            ->whereNotNull('student_cert_expires')
            ->whereBetween('student_cert_expires', [
                $now->toDateString(),
                $now->copy()->addDays(30)->toDateString(),
            ])
            ->count();

        return [
            'mora_over_90_days' => $moraOver90,
            'beneficiaries_turning_18' => $turning18,
            'student_certs_expiring' => $certExpiring,
        ];
    }
}
