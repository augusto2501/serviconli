<?php

namespace App\Modules\CashReconciliation\Services;

use App\Modules\Billing\Models\BillInvoice;
use App\Modules\Billing\Models\PaymentReceived;
use App\Modules\CashReconciliation\Models\CashReconAffiliations;
use App\Modules\CashReconciliation\Models\CashReconContributions;
use App\Modules\CashReconciliation\Models\CashReconCuentas;
use App\Modules\CashReconciliation\Models\DailyReconciliation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Cuadre diario — Flujo 10, DOCUMENTO_RECTOR §8.2.
 *
 * Línea 1 AFILIACIONES: recibos AFILIACION/REINGRESO × medio.
 * Línea 2 APORTES: recibos APORTE (SS+admin+intereses) × medio + provisión mora.
 * Línea 3 CUENTAS COBRO: pagos con cuenta de cobro / tipo CUENTA × medio.
 */
final class DailyReconciliationService
{
    /**
     * Obtiene o crea la reconciliación del día (ABIERTO).
     */
    public function getOrCreateForDate(Carbon|string $businessDate, ?int $userId = null): DailyReconciliation
    {
        $date = $businessDate instanceof Carbon ? $businessDate->toDateString() : (string) $businessDate;

        return DB::transaction(function () use ($date, $userId): DailyReconciliation {
            $existing = DailyReconciliation::query()->where('business_date', $date)->first();
            if ($existing !== null) {
                return $existing;
            }

            return DailyReconciliation::query()->create([
                'business_date' => $date,
                'user_id' => $userId,
                'status' => 'ABIERTO',
                'opened_at' => now(),
            ]);
        });
    }

    /**
     * Recalcula las 3 líneas desde `bill_payments_received` + facturas del día.
     */
    public function recalculate(DailyReconciliation $reconciliation): DailyReconciliation
    {
        if ($reconciliation->isClosed()) {
            throw new InvalidArgumentException('No se puede recalcular un cuadre ya cerrado.');
        }

        $date = $reconciliation->business_date->toDateString();

        $payments = PaymentReceived::query()
            ->whereDate('payment_date', $date)
            ->where('status', 'APLICADO')
            ->whereNotNull('invoice_id')
            ->with(['invoice.items'])
            ->get();

        $line1 = $this->emptyBuckets();
        $line2 = $this->emptyBuckets();
        $line2Ss = $line2Admin = $line2Mora = $provisionMora = 0;
        $line3 = $this->emptyBuckets();
        $line3Aff = $line3Contrib = $line3Admin = 0;

        $countAff = 0;

        foreach ($payments as $pr) {
            $inv = $pr->invoice;
            if ($inv === null || $inv->isAnulado()) {
                continue;
            }

            $amount = (int) $pr->amount_pesos;
            $method = $pr->payment_method ?? 'EFECTIVO';
            $tipo = $inv->tipo ?? '';

            if (in_array($tipo, ['AFILIACION', 'REINGRESO'], true)) {
                $countAff++;
                $this->addToBuckets($method, $amount, $line1);
                $line1['total_affiliation_value'] += $amount;
            } elseif ($tipo === 'APORTE') {
                $this->addToBuckets($method, $amount, $line2);
                [$ss, $adm, $mora] = $this->splitContributionItems($inv);
                $line2Ss += $ss;
                $line2Admin += $adm;
                $line2Mora += $mora;
            } elseif ($tipo === 'CUENTA' || $pr->cuenta_cobro_id !== null) {
                $this->addToBuckets($method, $amount, $line3);
                [$a, $c, $adm] = $this->splitCuentaItems($inv);
                $line3Aff += $a;
                $line3Contrib += $c;
                $line3Admin += $adm;
            }
        }

        return DB::transaction(function () use (
            $reconciliation, $line1, $line2, $line2Ss, $line2Admin, $line2Mora,
            $provisionMora, $line3, $line3Aff, $line3Contrib, $line3Admin, $countAff,
        ): DailyReconciliation {
            CashReconAffiliations::query()->updateOrCreate(
                ['reconciliation_id' => $reconciliation->id],
                [
                    'total_receipts' => $countAff,
                    'total_affiliation_value' => $line1['total_affiliation_value'],
                    'total_advisor_commission' => 0,
                    'total_efectivo' => $line1['total_efectivo'],
                    'total_consignacion' => $line1['total_consignacion'],
                    'total_credito' => $line1['total_credito'],
                    'total_cuenta_cobro' => $line1['total_cuenta_cobro'],
                ]
            );

            CashReconContributions::query()->updateOrCreate(
                ['reconciliation_id' => $reconciliation->id],
                [
                    'total_aporte_pos' => $line2Ss,
                    'total_admin' => $line2Admin,
                    'total_interest_mora' => $line2Mora,
                    'provision_mora' => $provisionMora,
                    'total_efectivo' => $line2['total_efectivo'],
                    'total_consignacion' => $line2['total_consignacion'],
                    'total_credito' => $line2['total_credito'],
                    'total_cuenta_cobro' => $line2['total_cuenta_cobro'],
                ]
            );

            CashReconCuentas::query()->updateOrCreate(
                ['reconciliation_id' => $reconciliation->id],
                [
                    'total_affiliations_cuentas' => $line3Aff,
                    'total_contributions_cuentas' => $line3Contrib,
                    'total_admin_cuentas' => $line3Admin,
                    'total_efectivo' => $line3['total_efectivo'],
                    'total_consignacion' => $line3['total_consignacion'],
                    'total_credito' => $line3['total_credito'],
                    'total_cuenta_cobro' => $line3['total_cuenta_cobro'],
                ]
            );

            return $reconciliation->load(['affiliationsLine', 'contributionsLine', 'cuentasLine']);
        });
    }

    /**
     * @return array{total_efectivo: int, total_consignacion: int, total_credito: int, total_cuenta_cobro: int, total_affiliation_value: int}
     */
    private function emptyBuckets(): array
    {
        return [
            'total_efectivo' => 0,
            'total_consignacion' => 0,
            'total_credito' => 0,
            'total_cuenta_cobro' => 0,
            'total_affiliation_value' => 0,
        ];
    }

    /**
     * @param  array{total_efectivo: int, total_consignacion: int, total_credito: int, total_cuenta_cobro: int, total_affiliation_value?: int}  $buckets
     */
    private function addToBuckets(string $method, int $amount, array &$buckets): void
    {
        $key = match ($method) {
            'EFECTIVO' => 'total_efectivo',
            'CONSIGNACION' => 'total_consignacion',
            'CREDITO' => 'total_credito',
            'CUENTA_COBRO' => 'total_cuenta_cobro',
            default => 'total_efectivo',
        };
        $buckets[$key] += $amount;
    }

    /**
     * @return array{0: int, 1: int, 2: int} SS, admin, mora
     */
    private function splitContributionItems(BillInvoice $invoice): array
    {
        $ss = $adm = $mora = 0;
        foreach ($invoice->items ?? [] as $item) {
            $c = mb_strtoupper((string) $item->concept);
            if (str_contains($c, 'SUBTOTAL')) {
                continue;
            }
            $amt = (int) $item->amount_pesos;
            if (str_contains($c, 'INTERESES') || str_contains($c, 'MORA')) {
                $mora += $amt;
            } elseif (str_contains($c, 'ADMINISTRACIÓN') || str_contains($c, 'ADMINISTRACION')) {
                $adm += $amt;
            } elseif (
                str_contains($c, 'SALUD') || str_contains($c, 'EPS')
                || str_contains($c, 'PENSIÓN') || str_contains($c, 'PENSION')
                || str_contains($c, 'ARL') || str_contains($c, 'CCF')
            ) {
                $ss += $amt;
            }
        }

        if ($ss + $adm + $mora === 0) {
            return [(int) $invoice->total_pesos, 0, 0];
        }

        return [$ss, $adm, $mora];
    }

    /**
     * @return array{0: int, 1: int, 2: int} affiliations part, contributions part, admin
     */
    private function splitCuentaItems(BillInvoice $invoice): array
    {
        $a = $c = $adm = 0;
        foreach ($invoice->items ?? [] as $item) {
            $cpt = mb_strtoupper((string) $item->concept);
            $amt = (int) $item->amount_pesos;
            if (str_contains($cpt, 'AFILIACIÓN') || str_contains($cpt, 'AFILIACION')) {
                $a += $amt;
            } elseif (str_contains($cpt, 'ADMINISTRACIÓN') || str_contains($cpt, 'ADMINISTRACION')) {
                $adm += $amt;
            } else {
                $c += $amt;
            }
        }

        if ($a + $c + $adm === 0) {
            return [0, (int) $invoice->total_pesos, 0];
        }

        return [$a, $c, $adm];
    }

    /**
     * Totales por ítem de línea para armar los 13 conceptos del cierre.
     *
     * @return array<string, int>
     */
    public function defaultThirteenConcepts(DailyReconciliation $r): array
    {
        $r->loadMissing(['affiliationsLine', 'contributionsLine', 'cuentasLine']);

        $l1 = $r->affiliationsLine;
        $l2 = $r->contributionsLine;
        $l3 = $r->cuentasLine;

        $ingresosEfectivo = ($l1?->total_efectivo ?? 0) + ($l2?->total_efectivo ?? 0) + ($l3?->total_efectivo ?? 0);

        $sumLine = static function ($line): int {
            if ($line === null) {
                return 0;
            }

            return (int) ($line->total_efectivo + $line->total_consignacion + $line->total_credito + $line->total_cuenta_cobro);
        };

        $l1t = $sumLine($l1);
        $l2t = $sumLine($l2);
        $l3t = $sumLine($l3);
        $totalDia = $l1t + $l2t + $l3t;

        return [
            'tramites' => 0,
            'corretaje' => 0,
            'aportes_ss' => $l2?->total_aporte_pos ?? 0,
            'intereses' => $l2?->total_interest_mora ?? 0,
            'administracion' => ($l2?->total_admin ?? 0) + ($l3?->total_admin_cuentas ?? 0),
            'ingresos_caja' => $ingresosEfectivo,
            'egresos_caja' => 0,
            'linea_afiliaciones' => $l1t,
            'linea_aportes' => $l2t,
            'linea_cuentas_cobro' => $l3t,
            'provision_mora' => $l2?->provision_mora ?? 0,
            'comisiones_asesor' => $l1?->total_advisor_commission ?? 0,
            'otros' => 0,
            'total_dia' => $totalDia,
        ];
    }

    /**
     * Total del cierre: usa `total_dia` si existe para evitar doble conteo.
     *
     * @param  array<string, int>  $concepts
     */
    public function sumConcepts(array $concepts): int
    {
        if (isset($concepts['total_dia'])) {
            return (int) $concepts['total_dia'];
        }

        return (int) array_sum($concepts);
    }
}
