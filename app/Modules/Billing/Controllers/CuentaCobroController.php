<?php

namespace App\Modules\Billing\Controllers;

use App\Modules\Billing\Models\CuentaCobro;
use App\Modules\Billing\Services\CuentaCobroPaymentService;
use App\Modules\Billing\Services\CuentaCobroService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class CuentaCobroController extends Controller
{
    public function __construct(
        private readonly CuentaCobroService $cuentaCobroService,
        private readonly CuentaCobroPaymentService $paymentService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = CuentaCobro::query()->with('payer');

        if ($request->filled('payer_id')) {
            $query->where('payer_id', $request->integer('payer_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return response()->json($query->orderByDesc('created_at')->paginate(20));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'payer_id' => 'required|integer|exists:afl_payers,id',
            'period_year' => 'required|integer|min:2020',
            'period_month' => 'required|integer|min:1|max:12',
            'mode' => 'required|in:PLENO,SOLO_APORTES,SOLO_AFILIACIONES',
            'batch_id' => 'nullable|integer|exists:pay_liquidation_batches,id',
        ]);

        $cuenta = $this->cuentaCobroService->generatePreCuenta(
            payerId: $data['payer_id'],
            periodYear: $data['period_year'],
            periodMonth: $data['period_month'],
            mode: $data['mode'],
            batchId: $data['batch_id'] ?? null,
        );

        return response()->json($cuenta->load('details'), 201);
    }

    public function show(int $id): JsonResponse
    {
        $cuenta = CuentaCobro::query()
            ->with(['payer', 'details.affiliate.person', 'invoices'])
            ->findOrFail($id);

        return response()->json($cuenta);
    }

    public function makeDefinitiva(Request $request, int $id): JsonResponse
    {
        $cuenta = CuentaCobro::query()->findOrFail($id);

        $data = $request->validate([
            'payment_date_1' => 'required|date',
            'payment_date_2' => 'required|date|after_or_equal:payment_date_1',
            'mora_days' => 'integer|min:0',
        ]);

        $result = $this->cuentaCobroService->makeDefinitiva(
            cuenta: $cuenta,
            paymentDate1: $data['payment_date_1'],
            paymentDate2: $data['payment_date_2'],
            moraDays: $data['mora_days'] ?? 0,
        );

        return response()->json($result);
    }

    public function pay(Request $request, int $id): JsonResponse
    {
        $cuenta = CuentaCobro::query()->findOrFail($id);

        $data = $request->validate([
            'payment_method' => 'required|in:EFECTIVO,CONSIGNACION',
            'amount_pesos' => 'required|integer|min:1',
            'bank_name' => 'nullable|string|max:100',
            'bank_reference' => 'nullable|string|max:50',
        ]);

        $invoice = $this->paymentService->pay(
            cuenta: $cuenta,
            paymentMethod: $data['payment_method'],
            amountPesos: $data['amount_pesos'],
            bankName: $data['bank_name'] ?? null,
            bankReference: $data['bank_reference'] ?? null,
            receivedBy: $request->user()?->name ?? 'system',
        );

        return response()->json($invoice, 201);
    }

    /** RF-078: regenerar pre-cuenta (borrador) */
    public function regenerate(int $id): JsonResponse
    {
        $cuenta = CuentaCobro::query()->findOrFail($id);

        $new = $this->cuentaCobroService->regeneratePreCuenta($cuenta);

        return response()->json($new->load('details'), 200);
    }

    /** RF-079: descarga PDF cuenta de cobro */
    public function pdf(int $id): \Illuminate\Http\Response
    {
        $cuenta = CuentaCobro::query()->findOrFail($id);

        return $this->cuentaCobroService->generatePdf($cuenta);
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $cuenta = CuentaCobro::query()->findOrFail($id);

        $data = $request->validate([
            'cancellation_reason' => 'required|string|max:64',
            'cancellation_motive' => 'required|string',
        ]);

        $result = $this->cuentaCobroService->cancel(
            cuenta: $cuenta,
            reason: $data['cancellation_reason'],
            motive: $data['cancellation_motive'],
            cancelledBy: $request->user()?->name ?? 'system',
        );

        return response()->json($result);
    }
}
