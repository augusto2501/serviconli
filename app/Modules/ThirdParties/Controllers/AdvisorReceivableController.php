<?php

namespace App\Modules\ThirdParties\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\ThirdParties\Models\AdvisorReceivable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class AdvisorReceivableController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AdvisorReceivable::class);

        $q = AdvisorReceivable::query()->with(['advisor', 'billInvoice']);

        if ($request->filled('advisor_id')) {
            $q->where('advisor_id', (int) $request->input('advisor_id'));
        }
        if ($request->filled('status')) {
            $q->where('status', $request->string('status')->toString());
        }

        $perPage = min(100, max(1, (int) $request->input('per_page', 15)));
        $paginator = $q->orderByDesc('id')->paginate($perPage);

        return response()->json([
            'data' => collect($paginator->items())->map(fn (AdvisorReceivable $r): array => [
                'id' => $r->id,
                'advisorId' => $r->advisor_id,
                'billInvoiceId' => $r->bill_invoice_id,
                'amountPesos' => (int) $r->amount_pesos,
                'status' => $r->status,
                'notes' => $r->notes,
            ])->values()->all(),
            'meta' => [
                'currentPage' => $paginator->currentPage(),
                'lastPage' => $paginator->lastPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function update(Request $request, AdvisorReceivable $advisorReceivable): JsonResponse
    {
        $this->authorize('update', $advisorReceivable);

        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(['PAGADA', 'ANULADA'])],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        if ($advisorReceivable->status !== 'PENDIENTE') {
            return response()->json([
                'message' => 'Solo se puede cambiar el estado desde PENDIENTE.',
            ], 422);
        }

        $advisorReceivable->fill($validated);
        $advisorReceivable->save();

        return response()->json([
            'id' => $advisorReceivable->id,
            'status' => $advisorReceivable->status,
            'notes' => $advisorReceivable->notes,
        ]);
    }
}
