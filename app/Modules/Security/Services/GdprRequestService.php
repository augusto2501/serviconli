<?php

namespace App\Modules\Security\Services;

use App\Modules\Security\Models\GdprRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * RF-110 — gestión de derechos del titular (Ley 1581/2012).
 *
 * Soporta: consulta, rectificación, supresión y revocación.
 *
 * @see DOCUMENTO_RECTOR §14.3
 */
final class GdprRequestService
{
    public const TYPES = ['CONSULTA', 'RECTIFICACION', 'SUPRESION', 'REVOCACION'];

    public const STATUSES = ['PENDIENTE', 'EN_PROCESO', 'RESUELTA', 'RECHAZADA'];

    public function create(int $affiliateId, string $type, ?string $description = null): GdprRequest
    {
        return GdprRequest::query()->create([
            'affiliate_id' => $affiliateId,
            'requested_by' => Auth::id(),
            'type' => $type,
            'description' => $description,
            'status' => 'PENDIENTE',
        ]);
    }

    public function resolve(GdprRequest $request, string $status, string $notes): GdprRequest
    {
        $request->update([
            'status' => $status,
            'resolution_notes' => $notes,
            'resolved_by' => Auth::id(),
            'resolved_at' => Carbon::now(),
        ]);

        return $request->fresh();
    }

    /** @return array<string, int> */
    public function summary(): array
    {
        $counts = GdprRequest::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        return [
            'pending' => ($counts['PENDIENTE'] ?? 0) + ($counts['EN_PROCESO'] ?? 0),
            'resolved' => $counts['RESUELTA'] ?? 0,
            'rejected' => $counts['RECHAZADA'] ?? 0,
        ];
    }
}
