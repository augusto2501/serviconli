<?php

namespace App\Modules\Affiliates\Controllers;

// RF-015 — vista consolidada backend (detalle operativo)

use App\Http\Controllers\Controller;
use App\Modules\Affiliates\Models\Affiliate;
use App\Modules\Affiliates\Models\AffiliateNote;
use App\Modules\Affiliates\Models\Beneficiary;
use Illuminate\Http\JsonResponse;

final class Ficha360Controller extends Controller
{
    public function show(Affiliate $affiliate): JsonResponse
    {
        $affiliate->load('person', 'status');

        $p = $affiliate->person;

        return response()->json([
            'affiliate' => [
                'id' => $affiliate->id,
                'clientType' => $affiliate->client_type?->value,
                'statusId' => $affiliate->status_id,
                'moraStatus' => $affiliate->mora_status,
                'operationalNotes' => $affiliate->operational_notes,
                'paymentNotes' => $affiliate->payment_notes,
            ],
            'person' => $p === null ? null : [
                'documentType' => $p->document_type,
                'documentNumber' => $p->document_number,
                'firstName' => $p->first_name,
                'firstSurname' => $p->first_surname,
                'email' => $p->email,
                'cellphone' => $p->cellphone,
            ],
            'counts' => [
                'beneficiaries' => Beneficiary::query()->where('affiliate_id', $affiliate->id)->count(),
                'notes' => AffiliateNote::query()->where('affiliate_id', $affiliate->id)->count(),
            ],
        ]);
    }
}
