<?php

namespace App\Modules\Security\Services;

use App\Modules\Advisors\Models\Advisor;
use App\Modules\Affiliates\Models\Affiliate;
use App\Modules\Billing\Models\PaymentReceived;
use App\Modules\CashReconciliation\Models\DailyReconciliation;
use App\Modules\CashReconciliation\Services\DailyReconciliationService;
use App\Modules\PILALiquidation\Enums\PilaLiquidationStatus;
use App\Modules\PILALiquidation\Models\PilaLiquidation;
use App\Modules\RegulatoryEngine\Models\AffiliateStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Reportes operativos — RF-115.
 *
 * Relación diaria de aportes, gestión cobro mora,
 * asociados por asesor, afiliados activos por empresa,
 * cuadre de caja del día, reporte fin de día.
 *
 * @see DOCUMENTO_RECTOR §15.2
 */
final class OperationalReportService
{
    public function __construct(
        private readonly DailyReconciliationService $reconciliationService,
    ) {}

    /**
     * RF-115: Relación diaria de aportes y afiliaciones.
     *
     * @return array<string, mixed>
     */
    public function dailyContributions(Carbon|string $date): array
    {
        $dateStr = $date instanceof Carbon ? $date->toDateString() : $date;

        $payments = PaymentReceived::query()
            ->whereDate('payment_date', $dateStr)
            ->where('status', 'APLICADO')
            ->with(['invoice'])
            ->get();

        $affiliations = 0;
        $contributions = 0;
        $totalPesos = 0;
        $byMethod = ['EFECTIVO' => 0, 'CONSIGNACION' => 0, 'CREDITO' => 0, 'CUENTA_COBRO' => 0];

        foreach ($payments as $pr) {
            $amount = (int) $pr->amount_pesos;
            $totalPesos += $amount;
            $method = $pr->payment_method ?? 'EFECTIVO';
            $byMethod[$method] = ($byMethod[$method] ?? 0) + $amount;

            $tipo = $pr->invoice?->tipo ?? '';
            if (in_array($tipo, ['AFILIACION', 'REINGRESO'], true)) {
                $affiliations++;
            } elseif ($tipo === 'APORTE') {
                $contributions++;
            }
        }

        return [
            'date' => $dateStr,
            'affiliations_count' => $affiliations,
            'contributions_count' => $contributions,
            'total_pesos' => $totalPesos,
            'by_payment_method' => $byMethod,
        ];
    }

    /**
     * RF-115: Gestión de cobro — asociados en mora.
     *
     * @return array<string, mixed>
     */
    public function moraReport(): array
    {
        $moraCodes = ['SUSPENDIDO', 'MORA_30', 'MORA_60', 'MORA_90', 'MORA_120', 'MORA_120_PLUS'];
        $statusIds = AffiliateStatus::query()
            ->whereIn('code', $moraCodes)
            ->pluck('id', 'code');

        $byLevel = [];
        foreach ($moraCodes as $code) {
            $id = $statusIds[$code] ?? null;
            $byLevel[$code] = $id !== null
                ? Affiliate::query()->whereNull('deleted_at')->where('status_id', $id)->count()
                : 0;
        }

        $totalMora = array_sum($byLevel);

        $topMora = Affiliate::query()
            ->whereNull('deleted_at')
            ->whereIn('status_id', $statusIds->values())
            ->with(['person:id,first_name,last_name1', 'status:id,code'])
            ->orderByDesc('status_id')
            ->limit(20)
            ->get()
            ->map(fn (Affiliate $a): array => [
                'affiliate_id' => $a->id,
                'name' => trim(($a->person?->first_name ?? '').' '.($a->person?->last_name1 ?? '')),
                'status' => $a->status?->code ?? '',
                'client_type' => $a->client_type?->value ?? '',
            ])
            ->values()
            ->all();

        return [
            'total_in_mora' => $totalMora,
            'by_level' => $byLevel,
            'top_delinquent' => $topMora,
        ];
    }

    /**
     * RF-115: Asociados por asesor.
     *
     * @return array<int, array<string, mixed>>
     */
    public function affiliatesByAdvisor(): array
    {
        return Advisor::query()
            ->withCount(['commissions as affiliates_count' => function ($q): void {
                $q->where('status', '!=', 'ANULADA');
            }])
            ->orderByDesc('affiliates_count')
            ->get()
            ->map(fn (Advisor $a): array => [
                'advisor_id' => $a->id,
                'code' => $a->code,
                'name' => trim($a->first_name.' '.$a->last_name),
                'affiliates_count' => (int) $a->affiliates_count,
                'commission_new' => (int) $a->commission_new,
                'commission_recurring' => (int) $a->commission_recurring,
            ])
            ->values()
            ->all();
    }

    /**
     * RF-115: Afiliados activos por empresa/pagador.
     *
     * @return array<int, array<string, mixed>>
     */
    public function activeAffiliatesByEmployer(): array
    {
        $activeCodes = ['ACTIVO', 'AFILIADO', 'PAGO_MES_SUBSIGUIENTE'];
        $activeStatusIds = AffiliateStatus::query()
            ->whereIn('code', $activeCodes)
            ->pluck('id');

        return DB::table('afl_affiliate_payer as ap')
            ->join('afl_affiliates as a', 'a.id', '=', 'ap.affiliate_id')
            ->join('afl_payers as p', 'p.id', '=', 'ap.payer_id')
            ->whereNull('ap.end_date')
            ->whereNull('a.deleted_at')
            ->whereIn('a.status_id', $activeStatusIds)
            ->select('p.id as payer_id', 'p.razon_social', DB::raw('COUNT(*) as active_count'))
            ->groupBy('p.id', 'p.razon_social')
            ->orderByDesc('active_count')
            ->get()
            ->map(fn ($row): array => [
                'payer_id' => $row->payer_id,
                'razon_social' => $row->razon_social,
                'active_count' => (int) $row->active_count,
            ])
            ->all();
    }

    /**
     * RF-115: Cuadre de caja del día.
     *
     * @return array<string, mixed>
     */
    public function cashReconciliation(Carbon|string $date): array
    {
        $dateStr = $date instanceof Carbon ? $date->toDateString() : $date;

        $recon = DailyReconciliation::query()
            ->where('business_date', $dateStr)
            ->with(['affiliationsLine', 'contributionsLine', 'cuentasLine'])
            ->first();

        if ($recon === null) {
            return [
                'date' => $dateStr,
                'status' => 'NO_ABIERTO',
                'concepts' => [],
                'grand_total_pesos' => 0,
            ];
        }

        $concepts = $this->reconciliationService->defaultThirteenConcepts($recon);

        return [
            'date' => $dateStr,
            'status' => $recon->status,
            'concepts' => $concepts,
            'grand_total_pesos' => $this->reconciliationService->sumConcepts($concepts),
        ];
    }

    /**
     * RF-115: Reporte de fin de día (resumen ejecutivo).
     *
     * @return array<string, mixed>
     */
    public function endOfDayReport(Carbon|string $date): array
    {
        $dateStr = $date instanceof Carbon ? $date->toDateString() : $date;
        $dateCarbon = Carbon::parse($dateStr);

        $daily = $this->dailyContributions($dateCarbon);
        $cash = $this->cashReconciliation($dateCarbon);

        $liquidationsConfirmed = PilaLiquidation::query()
            ->where('status', PilaLiquidationStatus::Confirmed)
            ->whereDate('updated_at', $dateStr)
            ->count();

        return [
            'date' => $dateStr,
            'payments' => $daily,
            'cash_reconciliation' => $cash,
            'liquidations_confirmed_today' => $liquidationsConfirmed,
        ];
    }
}
