<?php

namespace App\Modules\CashReconciliation\Controllers;

use App\Modules\CashReconciliation\Models\DailyReconciliation;
use App\Modules\CashReconciliation\Services\DailyCloseService;
use App\Modules\CashReconciliation\Services\DailyReconciliationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class CashReconciliationController extends Controller
{
    public function __construct(
        private readonly DailyReconciliationService $reconciliationService,
        private readonly DailyCloseService $closeService,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $date = $request->query('date', Carbon::today()->toDateString());

        $r = DailyReconciliation::query()
            ->where('business_date', $date)
            ->with(['affiliationsLine', 'contributionsLine', 'cuentasLine', 'dailyClose', 'user'])
            ->first();

        if ($r === null) {
            return response()->json([
                'business_date' => $date,
                'status' => 'NONE',
                'message' => 'No hay cuadre para esta fecha. Use POST recalculate para crear.',
            ]);
        }

        return response()->json($r);
    }

    public function recalculate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date' => 'required|date',
        ]);

        $r = $this->reconciliationService->getOrCreateForDate(Carbon::parse($data['date']), $request->user()?->id);
        if ($r->isClosed()) {
            return response()->json(['message' => 'El cuadre ya está cerrado.'], 422);
        }

        $this->reconciliationService->recalculate($r);

        return response()->json($r->fresh()->load(['affiliationsLine', 'contributionsLine', 'cuentasLine']));
    }

    public function close(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date' => 'required|date',
            'concepts' => 'nullable|array',
            'concepts.*' => 'integer|min:0',
        ]);

        $r = $this->reconciliationService->getOrCreateForDate(
            Carbon::parse($data['date']),
            $request->user()?->id
        );

        $close = $this->closeService->close(
            $r,
            $request->user()?->id,
            $data['concepts'] ?? null,
        );

        return response()->json([
            'reconciliation' => $r->fresh()->load(['affiliationsLine', 'contributionsLine', 'cuentasLine']),
            'close' => $close,
        ]);
    }
}
