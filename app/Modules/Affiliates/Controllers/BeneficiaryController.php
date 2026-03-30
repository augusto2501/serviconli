<?php

namespace App\Modules\Affiliates\Controllers;

// RF-017 — API mínima beneficiarios (wizard / ficha 360 en fases UI posteriores)

use App\Http\Controllers\Controller;
use App\Modules\Affiliates\Models\Affiliate;
use App\Modules\Affiliates\Models\Beneficiary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class BeneficiaryController extends Controller
{
    public function index(Affiliate $affiliate): JsonResponse
    {
        $this->authorize('view', $affiliate);

        $rows = Beneficiary::query()->where('affiliate_id', $affiliate->id)->orderBy('id')->get();

        return response()->json([
            'data' => $rows->map(fn (Beneficiary $b): array => $this->toArray($b))->all(),
        ]);
    }

    public function store(Request $request, Affiliate $affiliate): JsonResponse
    {
        $this->authorize('update', $affiliate);

        $validated = $request->validate([
            'document_number' => ['required', 'string', 'max:32'],
            'document_type' => ['nullable', 'string', 'max:16'],
            'first_name' => ['nullable', 'string', 'max:255'],
            'surnames' => ['nullable', 'string', 'max:255'],
            'parentesco' => ['nullable', 'string', 'max:64'],
        ]);

        $b = Beneficiary::query()->create([
            'affiliate_id' => $affiliate->id,
            ...$validated,
        ]);

        return response()->json($this->toArray($b), 201);
    }

    /** @return array<string, mixed> */
    private function toArray(Beneficiary $b): array
    {
        return [
            'id' => $b->id,
            'affiliateId' => $b->affiliate_id,
            'documentType' => $b->document_type,
            'documentNumber' => $b->document_number,
            'firstName' => $b->first_name,
            'surnames' => $b->surnames,
            'parentesco' => $b->parentesco,
        ];
    }
}
