<?php

namespace App\Modules\Billing\Services;

use App\Modules\Affiliates\Models\Affiliate;
use App\Modules\Affiliations\Models\AffiliatePayer;
use App\Modules\Affiliations\Models\Payer;
use App\Modules\Billing\Models\CuentaCobro;
use App\Modules\Billing\Models\CuentaCobroDetail;
use App\Modules\Billing\Models\ServiceContract;
use App\Modules\PILALiquidation\Models\LiquidationBatch;
use App\Modules\RegulatoryEngine\Services\MoraInterestService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * RN-16: Cuenta de cobro en 3 modos + pre-cuenta.
 *
 * PLENO          → aportes SS + admin + afiliación
 * SOLO_APORTES   → solo aportes SS (EPS+AFP+ARL+CCF)
 * SOLO_AFILIACIONES → solo cuota administración + afiliación
 *
 * Flujo:
 *   Pre-cuenta (PRE_CUENTA) → no afecta datos
 *   Definitiva (DEFINITIVA) → doble fecha (RN-17): payment_date_1 (oportuno), payment_date_2 (con mora)
 *   Intereses se calculan solo sobre base SS (RN-13)
 *
 * Portado de Access Form_008:15344 + Form_Sub.
 *
 * @see DOCUMENTO_RECTOR §5 RN-16, Flujo 5
 */
final class CuentaCobroService
{
    public function __construct(
        private readonly MoraInterestService $moraInterestService,
        private readonly ConsecutiveService $consecutiveService,
    ) {}

    /**
     * Genera pre-cuenta sin afectar datos.
     */
    public function generatePreCuenta(
        int $payerId,
        int $periodYear,
        int $periodMonth,
        string $mode = 'PLENO',
        ?int $batchId = null,
    ): CuentaCobro {
        $this->validateMode($mode);
        $payer = Payer::query()->findOrFail($payerId);

        return DB::transaction(function () use ($payer, $periodYear, $periodMonth, $mode, $batchId) {
            $details = $this->collectDetails($payer, $periodYear, $periodMonth, $mode, $batchId);

            $totals = $this->sumDetails($details, $mode);

            $cuenta = CuentaCobro::query()->create([
                'payer_id' => $payer->id,
                'batch_id' => $batchId,
                'cuenta_number' => $this->consecutiveService->next('CC'),
                'period_year' => $periodYear,
                'period_month' => $periodMonth,
                'period_cobro' => sprintf('%04d-%02d', $periodYear, $periodMonth),
                'period_servicio' => sprintf('%04d-%02d', $periodYear, $periodMonth),
                'generation_mode' => $mode,
                'total_eps' => $totals['eps'],
                'total_afp' => $totals['afp'],
                'total_arl' => $totals['arl'],
                'total_ccf' => $totals['ccf'],
                'total_admin' => $totals['admin'],
                'total_affiliation' => $totals['affiliation'],
                'total_1' => $totals['total_1'],
                'status' => 'PRE_CUENTA',
            ]);

            foreach ($details as $d) {
                CuentaCobroDetail::query()->create([...$d, 'cuenta_cobro_id' => $cuenta->id]);
            }

            return $cuenta->load('details');
        });
    }

    /**
     * Convierte una pre-cuenta en definitiva con doble fecha de pago (RN-17).
     */
    public function makeDefinitiva(
        CuentaCobro $cuenta,
        string $paymentDate1,
        string $paymentDate2,
        int $moraDays = 0,
    ): CuentaCobro {
        if (! $cuenta->isPreCuenta()) {
            throw new InvalidArgumentException('Solo se puede convertir en definitiva una PRE_CUENTA.');
        }

        $baseSS = $cuenta->total_eps + $cuenta->total_afp + $cuenta->total_arl + $cuenta->total_ccf;
        $interestMora = 0;

        if ($moraDays > 0 && $baseSS > 0) {
            $interestMora = $this->moraInterestService->interestPesos($baseSS, $moraDays);
        }

        $total2 = $cuenta->total_1 + $interestMora;

        $cuenta->update([
            'status' => 'DEFINITIVA',
            'payment_date_1' => $paymentDate1,
            'payment_date_2' => $paymentDate2,
            'interest_mora' => $interestMora,
            'total_2' => $total2,
        ]);

        return $cuenta->fresh();
    }

    /**
     * Anula una cuenta de cobro.
     */
    public function cancel(CuentaCobro $cuenta, string $reason, string $motive, string $cancelledBy): CuentaCobro
    {
        if ($cuenta->isPagada()) {
            throw new InvalidArgumentException('No se puede anular una cuenta ya pagada.');
        }

        if ($cuenta->isAnulada()) {
            throw new InvalidArgumentException('La cuenta ya está anulada.');
        }

        $cuenta->update([
            'status' => 'ANULADA',
            'cancellation_reason' => $reason,
            'cancellation_motive' => $motive,
            'cancelled_by' => $cancelledBy,
        ]);

        return $cuenta->fresh();
    }

    /**
     * Recoge detalle por afiliado desde lote confirmado o vínculos activos.
     *
     * @return array<array>
     */
    private function collectDetails(
        Payer $payer,
        int $periodYear,
        int $periodMonth,
        string $mode,
        ?int $batchId,
    ): array {
        $details = [];

        if ($batchId !== null) {
            $batch = LiquidationBatch::query()
                ->where('id', $batchId)
                ->where('payer_id', $payer->id)
                ->firstOrFail();

            $lines = $batch->lines()
                ->where('line_status', 'INCLUIDO')
                ->get();

            foreach ($lines as $line) {
                $details[] = $this->lineToDetail($line, $mode, $payer);
            }
        } else {
            $links = AffiliatePayer::query()
                ->where('payer_id', $payer->id)
                ->whereNull('end_date')
                ->where('status', 'ACTIVE')
                ->with('affiliate.currentSocialSecurityProfile')
                ->get();

            foreach ($links as $link) {
                $affiliate = $link->affiliate;
                if ($affiliate === null) {
                    continue;
                }
                $details[] = $this->affiliateToDetail($affiliate, $link, $mode, $payer);
            }
        }

        return $details;
    }

    private function lineToDetail(mixed $line, string $mode, Payer $payer): array
    {
        $contract = $this->getContract($payer);
        $adminFee = (int) ($line->admin_fee ?? $contract?->tarifa_admin_pesos ?? 0);
        $affiliationFee = (int) ($contract?->tarifa_affiliation_pesos ?? 0);

        return [
            'affiliate_id' => $line->affiliate_id,
            'health_pesos' => $this->includeAportes($mode) ? (int) $line->health_total : 0,
            'pension_pesos' => $this->includeAportes($mode) ? (int) $line->pension_total : 0,
            'arl_pesos' => $this->includeAportes($mode) ? (int) $line->arl_total : 0,
            'ccf_pesos' => $this->includeAportes($mode) ? (int) $line->ccf_total : 0,
            'admin_pesos' => $this->includeAdmin($mode) ? $adminFee : 0,
            'affiliation_pesos' => $this->includeAdmin($mode) ? $affiliationFee : 0,
            'total_pesos' => $this->calculateDetailTotal($line, $mode, $adminFee, $affiliationFee),
        ];
    }

    private function affiliateToDetail(Affiliate $affiliate, AffiliatePayer $link, string $mode, Payer $payer): array
    {
        $ssProfile = $affiliate->currentSocialSecurityProfile;
        $contract = $this->getContract($payer);
        $adminFee = (int) ($ssProfile?->admin_fee ?? $contract?->tarifa_admin_pesos ?? 0);
        $affiliationFee = (int) ($contract?->tarifa_affiliation_pesos ?? 0);

        return [
            'affiliate_id' => $affiliate->id,
            'health_pesos' => 0,
            'pension_pesos' => 0,
            'arl_pesos' => 0,
            'ccf_pesos' => 0,
            'admin_pesos' => $this->includeAdmin($mode) ? $adminFee : 0,
            'affiliation_pesos' => $this->includeAdmin($mode) ? $affiliationFee : 0,
            'total_pesos' => $this->includeAdmin($mode) ? ($adminFee + $affiliationFee) : 0,
        ];
    }

    private function calculateDetailTotal(mixed $line, string $mode, int $adminFee, int $affiliationFee): int
    {
        $ss = $this->includeAportes($mode)
            ? (int) $line->health_total + (int) $line->pension_total + (int) $line->arl_total + (int) $line->ccf_total
            : 0;
        $admin = $this->includeAdmin($mode) ? ($adminFee + $affiliationFee) : 0;

        return $ss + $admin;
    }

    /**
     * @return array{eps: int, afp: int, arl: int, ccf: int, admin: int, affiliation: int, total_1: int}
     */
    private function sumDetails(array $details, string $mode): array
    {
        $eps = $afp = $arl = $ccf = $admin = $affiliation = 0;

        foreach ($details as $d) {
            $eps += $d['health_pesos'];
            $afp += $d['pension_pesos'];
            $arl += $d['arl_pesos'];
            $ccf += $d['ccf_pesos'];
            $admin += $d['admin_pesos'];
            $affiliation += $d['affiliation_pesos'];
        }

        return [
            'eps' => $eps,
            'afp' => $afp,
            'arl' => $arl,
            'ccf' => $ccf,
            'admin' => $admin,
            'affiliation' => $affiliation,
            'total_1' => $eps + $afp + $arl + $ccf + $admin + $affiliation,
        ];
    }

    private function getContract(Payer $payer): ?ServiceContract
    {
        return ServiceContract::query()
            ->where('payer_id', $payer->id)
            ->where('status', 'ACTIVO')
            ->latest('vigencia_start')
            ->first();
    }

    private function includeAportes(string $mode): bool
    {
        return in_array($mode, ['PLENO', 'SOLO_APORTES'], true);
    }

    private function includeAdmin(string $mode): bool
    {
        return in_array($mode, ['PLENO', 'SOLO_AFILIACIONES'], true);
    }

    /**
     * RF-078: regenerar pre-cuenta — borra la existente y crea una nueva.
     * Solo permitido en estado PRE_CUENTA (borrador no afecta datos).
     */
    public function regeneratePreCuenta(CuentaCobro $cuenta): CuentaCobro
    {
        if (! $cuenta->isPreCuenta()) {
            throw new InvalidArgumentException('Solo se puede regenerar una PRE_CUENTA (borrador).');
        }

        $payerId = $cuenta->payer_id;
        $periodYear = $cuenta->period_year;
        $periodMonth = $cuenta->period_month;
        $mode = $cuenta->generation_mode;
        $batchId = $cuenta->batch_id;

        CuentaCobroDetail::query()->where('cuenta_cobro_id', $cuenta->id)->delete();
        $cuenta->delete();

        return $this->generatePreCuenta($payerId, $periodYear, $periodMonth, $mode, $batchId);
    }

    /** RF-079: genera PDF cuenta de cobro definitiva */
    public function generatePdf(CuentaCobro $cuenta): \Illuminate\Http\Response
    {
        $cuenta->load('details.affiliate.person', 'payer');
        $numberToWords = app(NumberToWordsService::class);

        $pdf = Pdf::loadView('pdf.cuenta-cobro', [
            'cuenta' => $cuenta,
            'payer' => $cuenta->payer,
            'details' => $cuenta->details,
            'totalWords' => $numberToWords->convert($cuenta->total_2 ?? $cuenta->total_1),
        ]);

        return $pdf->download("cuenta-cobro-{$cuenta->cuenta_number}.pdf");
    }

    private function validateMode(string $mode): void
    {
        if (! in_array($mode, ['PLENO', 'SOLO_APORTES', 'SOLO_AFILIACIONES'], true)) {
            throw new InvalidArgumentException("Modo de generación inválido: {$mode}");
        }
    }
}
