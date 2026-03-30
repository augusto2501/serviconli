<?php

namespace App\Modules\Affiliates\Services;

// RF-015 — vista consolidada: SS, pagador, aportes (PILA), facturas, excepciones; portales/documentos pendientes de módulos

use App\Modules\Affiliates\Models\Affiliate;
use App\Modules\Affiliates\Models\AffiliateNote;
use App\Modules\Affiliates\Models\Beneficiary;
use App\Modules\Affiliates\Models\Person;
use App\Modules\Affiliates\Models\PortalCredential;
use App\Modules\Affiliations\Models\AffiliatePayer;
use App\Modules\Affiliations\Models\SocialSecurityProfile;
use App\Modules\Billing\Models\BillInvoice;
use App\Modules\PILALiquidation\Enums\PilaLiquidationStatus;
use App\Modules\PILALiquidation\Models\PilaLiquidation;
use App\Modules\PILALiquidation\Models\PilaLiquidationLine;
use App\Modules\RegulatoryEngine\Models\OperationalException;
use App\Modules\RegulatoryEngine\Models\SSEntity;
use App\Modules\RegulatoryEngine\Services\OperationalExceptionService;

final class Ficha360ViewBuilder
{
    public function __construct(
        private readonly OperationalExceptionService $operationalExceptions,
    ) {}

    /** @return array<string, mixed> */
    public function build(Affiliate $affiliate): array
    {
        $affiliate->load([
            'person',
            'status',
            'currentSocialSecurityProfile.epsEntity',
            'currentSocialSecurityProfile.afpEntity',
            'currentSocialSecurityProfile.arlEntity',
            'currentSocialSecurityProfile.ccfEntity',
        ]);

        $p = $affiliate->person;
        $ssp = $affiliate->currentSocialSecurityProfile;

        $payerLink = AffiliatePayer::query()
            ->where('affiliate_id', $affiliate->id)
            ->whereNull('end_date')
            ->with('payer')
            ->orderByDesc('start_date')
            ->first();

        $beneficiaryCount = Beneficiary::query()->where('affiliate_id', $affiliate->id)->count();
        $noteCount = AffiliateNote::query()->where('affiliate_id', $affiliate->id)->count();
        $ssProfileCount = SocialSecurityProfile::query()->where('affiliate_id', $affiliate->id)->count();

        $contributions = $this->contributionsFromPila($affiliate->id);
        $lastPaid = $this->lastPaidPeriodFromLines($affiliate->id);

        $invoices = BillInvoice::query()
            ->where('affiliate_id', $affiliate->id)
            ->orderByDesc('id')
            ->limit(15)
            ->get();

        $exceptions = $this->operationalExceptions->activeForTarget('AFFILIATE', $affiliate->id, now());

        $recentNotes = AffiliateNote::query()
            ->where('affiliate_id', $affiliate->id)
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        $recentBeneficiaries = Beneficiary::query()
            ->where('affiliate_id', $affiliate->id)
            ->orderByDesc('id')
            ->limit(30)
            ->get();

        $portalCredentials = PortalCredential::query()
            ->where('affiliate_id', $affiliate->id)
            ->orderBy('portal_type')
            ->get();

        return [
            'affiliate' => [
                'id' => $affiliate->id,
                'clientType' => $affiliate->client_type?->value,
                'statusId' => $affiliate->status_id,
                'statusCode' => $affiliate->status?->code,
                'statusName' => $affiliate->status?->name,
                'moraStatus' => $affiliate->mora_status,
                'statusIndicator' => $this->statusIndicator($affiliate),
                'operationalNotes' => $affiliate->operational_notes,
                'paymentNotes' => $affiliate->payment_notes,
                'ipsCode' => $affiliate->ips_code,
                'isType51' => $affiliate->is_type_51,
                'subtipo' => $affiliate->subtipo,
            ],
            'person' => $p === null ? null : $this->personToArray($p),
            'socialSecurity' => [
                'profilesCount' => $ssProfileCount,
                'current' => $this->socialSecurityProfileToArray($ssp),
            ],
            'payer' => [
                'current' => $this->payerLinkToArray($payerLink),
            ],
            'counts' => [
                'beneficiaries' => $beneficiaryCount,
                'notes' => $noteCount,
            ],
            'beneficiaries' => [
                'total' => $beneficiaryCount,
                'items' => $recentBeneficiaries->map(fn (Beneficiary $b): array => $this->beneficiaryToArray($b))->values()->all(),
            ],
            'notes' => [
                'total' => $noteCount,
                'recent' => $recentNotes->map(fn (AffiliateNote $n): array => [
                    'id' => $n->id,
                    'userId' => $n->user_id,
                    'noteType' => $n->note_type,
                    'note' => $n->note,
                    'createdAt' => $n->created_at?->toIso8601String(),
                ])->values()->all(),
            ],
            'contributions' => [
                'lastPaidPeriod' => $lastPaid,
                'pilaLiquidations' => $contributions,
            ],
            'invoices' => $invoices->map(fn (BillInvoice $inv): array => [
                'id' => $inv->id,
                'publicNumber' => $inv->public_number,
                'tipo' => $inv->tipo,
                'paymentMethod' => $inv->payment_method,
                'totalPesos' => $inv->total_pesos,
                'estado' => $inv->estado,
                'createdAt' => $inv->created_at?->toIso8601String(),
            ])->values()->all(),
            'operationalExceptions' => $exceptions->map(fn (OperationalException $e): array => [
                'id' => $e->id,
                'exceptionType' => $e->exception_type,
                'reason' => $e->reason,
                'validFrom' => $e->valid_from?->toDateString(),
                'validUntil' => $e->valid_until?->toDateString(),
            ])->values()->all(),
            'portals' => [
                'available' => true,
                'encryptionEnabled' => (bool) config('serviconli.portal_credentials.encrypt', false),
                'items' => $portalCredentials->map(fn (PortalCredential $c): array => [
                    'id' => $c->id,
                    'portalType' => $c->portal_type->value,
                    'username' => $c->username,
                    'password' => $c->password,
                    'notes' => $c->notes,
                ])->values()->all(),
            ],
            'documents' => [
                'available' => false,
                'hint' => 'Los documentos asociados se expondrán desde el módulo de gestión documental.',
            ],
        ];
    }

    private function statusIndicator(Affiliate $affiliate): string
    {
        $m = $affiliate->mora_status;
        if ($m === null || $m === '') {
            return 'NEUTRAL';
        }
        $u = strtoupper((string) $m);
        if (str_contains($u, 'MORA') || str_contains($u, 'MOROSO')) {
            return 'RISK';
        }
        if (str_contains($u, 'AL_DIA') || str_contains($u, 'AL DIA') || $u === 'OK') {
            return 'OK';
        }

        return 'NEUTRAL';
    }

    /** @return array<string, mixed>|null */
    private function socialSecurityProfileToArray(?SocialSecurityProfile $ssp): ?array
    {
        if ($ssp === null) {
            return null;
        }

        return [
            'id' => $ssp->id,
            'validFrom' => $ssp->valid_from?->toDateString(),
            'validUntil' => $ssp->valid_until?->toDateString(),
            'eps' => $this->ssEntityToArray($ssp->epsEntity),
            'afp' => $this->ssEntityToArray($ssp->afpEntity),
            'arl' => $this->ssEntityToArray($ssp->arlEntity),
            'ccf' => $this->ssEntityToArray($ssp->ccfEntity),
            'arlRiskClass' => $ssp->arl_risk_class,
        ];
    }

    /** @return array<string, mixed>|null */
    private function ssEntityToArray(?SSEntity $e): ?array
    {
        if ($e === null) {
            return null;
        }

        return [
            'id' => $e->id,
            'name' => $e->name,
            'pilaCode' => $e->pila_code,
            'type' => $e->type,
        ];
    }

    /** @return array<string, mixed> */
    private function personToArray(Person $p): array
    {
        return [
            'documentType' => $p->document_type,
            'documentNumber' => $p->document_number,
            'firstName' => $p->first_name,
            'secondName' => $p->second_name,
            'firstSurname' => $p->first_surname,
            'secondSurname' => $p->second_surname,
            'birthDate' => $p->birth_date?->toDateString(),
            'gender' => $p->gender,
            'maritalStatus' => $p->marital_status,
            'address' => $p->address,
            'neighborhood' => $p->neighborhood,
            'cityName' => $p->city_name,
            'departmentName' => $p->department_name,
            'phone1' => $p->phone1,
            'phone2' => $p->phone2,
            'cellphone' => $p->cellphone,
            'email' => $p->email,
            'isForeigner' => $p->is_foreigner,
        ];
    }

    /** @return array<string, mixed>|null */
    private function payerLinkToArray(?AffiliatePayer $link): ?array
    {
        if ($link === null) {
            return null;
        }

        $pay = $link->payer;

        return [
            'affiliatePayerId' => $link->id,
            'contributorTypeCode' => $link->contributor_type_code,
            'startDate' => $link->start_date?->toDateString(),
            'endDate' => $link->end_date?->toDateString(),
            'enterpriseCode' => $link->enterprise_code,
            'enterpriseName' => $link->enterprise_name,
            'salary' => $link->salary,
            'payer' => $pay === null ? null : [
                'id' => $pay->id,
                'nit' => $pay->nit,
                'digitoVerificacion' => $pay->digito_verificacion,
                'razonSocial' => $pay->razon_social,
                'status' => $pay->status,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function beneficiaryToArray(Beneficiary $b): array
    {
        return [
            'id' => $b->id,
            'documentType' => $b->document_type,
            'documentNumber' => $b->document_number,
            'firstName' => $b->first_name,
            'surnames' => $b->surnames,
            'parentesco' => $b->parentesco,
            'birthDate' => $b->birth_date?->toDateString(),
            'gender' => $b->gender,
        ];
    }

    /**
     * Liquidaciones PILA confirmadas con líneas (historial de aportes por período).
     *
     * @return list<array<string, mixed>>
     */
    private function contributionsFromPila(int $affiliateId): array
    {
        $liquidations = PilaLiquidation::query()
            ->where('affiliate_id', $affiliateId)
            ->where('status', PilaLiquidationStatus::Confirmed)
            ->with('lines')
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->limit(25)
            ->get();

        $out = [];
        foreach ($liquidations as $liq) {
            $linesOut = [];
            foreach ($liq->lines as $line) {
                $linesOut[] = $this->liquidationLineToArray($line);
            }
            $out[] = [
                'publicId' => $liq->public_id,
                'paymentDate' => $liq->payment_date?->toDateString(),
                'totalSocialSecurityPesos' => $liq->total_social_security_pesos,
                'lines' => $linesOut,
            ];
        }

        return $out;
    }

    /** @return array<string, mixed> */
    private function liquidationLineToArray(PilaLiquidationLine $line): array
    {
        return [
            'periodYear' => $line->period_year,
            'periodMonth' => $line->period_month,
            'rawIbcPesos' => $line->raw_ibc_pesos,
            'ibcRoundedPesos' => $line->ibc_rounded_pesos,
            'totalSocialSecurityPesos' => $line->total_social_security_pesos,
            'daysLate' => $line->days_late,
        ];
    }

    /**
     * Último período con aporte registrado en líneas PILA confirmadas.
     *
     * @return array{year: int, month: int}|null
     */
    private function lastPaidPeriodFromLines(int $affiliateId): ?array
    {
        $row = PilaLiquidationLine::query()
            ->join('pila_liquidations', 'pila_liquidations.id', '=', 'pila_liquidation_lines.pila_liquidation_id')
            ->where('pila_liquidations.affiliate_id', $affiliateId)
            ->where('pila_liquidations.status', PilaLiquidationStatus::Confirmed->value)
            ->orderByDesc('pila_liquidation_lines.period_year')
            ->orderByDesc('pila_liquidation_lines.period_month')
            ->select(['pila_liquidation_lines.period_year', 'pila_liquidation_lines.period_month'])
            ->first();

        if ($row === null) {
            return null;
        }

        return [
            'year' => (int) $row->period_year,
            'month' => (int) $row->period_month,
        ];
    }
}
