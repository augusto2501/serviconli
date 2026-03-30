<?php

namespace App\Modules\Affiliates\Controllers;

// RF-019 — notas por afiliado

use App\Http\Controllers\Controller;
use App\Modules\Affiliates\Models\Affiliate;
use App\Modules\Affiliates\Models\AffiliateNote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class AffiliateNoteController extends Controller
{
    public function index(Affiliate $affiliate): JsonResponse
    {
        $this->authorize('view', $affiliate);

        $rows = AffiliateNote::query()->where('affiliate_id', $affiliate->id)->orderByDesc('id')->get();

        return response()->json([
            'data' => $rows->map(fn (AffiliateNote $n): array => $this->toArray($n))->all(),
        ]);
    }

    public function store(Request $request, Affiliate $affiliate): JsonResponse
    {
        $this->authorize('update', $affiliate);

        $validated = $request->validate([
            'note' => ['required', 'string', 'max:65535'],
            'note_type' => ['required', 'string', Rule::in(['ADMINISTRATIVA', 'MEDICA', 'GENERAL', 'PAGO'])],
        ]);

        $n = AffiliateNote::query()->create([
            'affiliate_id' => $affiliate->id,
            'note' => $validated['note'],
            'note_type' => $validated['note_type'],
            'user_id' => $request->user()->id,
            'created_at' => now(),
        ]);

        return response()->json($this->toArray($n), 201);
    }

    /** @return array<string, mixed> */
    private function toArray(AffiliateNote $n): array
    {
        return [
            'id' => $n->id,
            'affiliateId' => $n->affiliate_id,
            'userId' => $n->user_id,
            'note' => $n->note,
            'noteType' => $n->note_type,
            'createdAt' => $n->created_at?->toIso8601String(),
        ];
    }
}
