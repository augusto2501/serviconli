<?php

namespace App\Modules\Billing\Controllers;

use App\Modules\Billing\Models\BillInvoice;
use App\Modules\Billing\Services\InvoiceCancellationService;
use App\Modules\Billing\Services\NumberToWordsService;
use App\Modules\Billing\Services\ReciboCajaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class InvoiceController extends Controller
{
    public function __construct(
        private readonly ReciboCajaService $reciboCajaService,
        private readonly InvoiceCancellationService $cancellationService,
        private readonly NumberToWordsService $numberToWordsService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = BillInvoice::query()->with(['affiliate.person', 'payer']);

        if ($request->filled('tipo')) {
            $query->where('tipo', $request->input('tipo'));
        }
        if ($request->filled('estado')) {
            $query->where('estado', $request->input('estado'));
        }
        if ($request->filled('fecha_from')) {
            $query->where('fecha', '>=', $request->input('fecha_from'));
        }
        if ($request->filled('fecha_to')) {
            $query->where('fecha', '<=', $request->input('fecha_to'));
        }

        return response()->json($query->orderByDesc('created_at')->paginate(20));
    }

    public function show(int $id): JsonResponse
    {
        $invoice = BillInvoice::query()
            ->with(['affiliate.person', 'payer', 'items', 'payments'])
            ->findOrFail($id);

        $totalWords = $this->numberToWordsService->convert((int) $invoice->total_pesos);

        return response()->json([
            'invoice' => $invoice,
            'total_in_words' => $totalWords,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tipo' => 'required|in:AFILIACION,APORTE,REINGRESO,CUENTA,CAJA_GENERAL',
            'payment_method' => 'required|in:EFECTIVO,CONSIGNACION,CREDITO,CUENTA_COBRO',
            'items' => 'required|array|min:1',
            'items.*.concept' => 'required|string|max:100',
            'items.*.amount_pesos' => 'required|integer|min:0',
            'affiliate_id' => 'nullable|integer|exists:afl_affiliates,id',
            'payer_id' => 'nullable|integer|exists:afl_payers,id',
            'bank_name' => 'nullable|string|max:100',
            'bank_reference' => 'nullable|string|max:50',
        ]);

        $invoice = $this->reciboCajaService->createReceipt(
            tipo: $data['tipo'],
            paymentMethod: $data['payment_method'],
            items: $data['items'],
            affiliateId: $data['affiliate_id'] ?? null,
            payerId: $data['payer_id'] ?? null,
            bankName: $data['bank_name'] ?? null,
            bankReference: $data['bank_reference'] ?? null,
            receivedBy: $request->user()?->name ?? 'system',
        );

        return response()->json($invoice, 201);
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $invoice = BillInvoice::query()->findOrFail($id);

        $data = $request->validate([
            'cancellation_reason' => 'required|string|max:64',
            'cancellation_motive' => 'required|string',
        ]);

        $result = $this->cancellationService->cancel(
            invoice: $invoice,
            cancellationReason: $data['cancellation_reason'],
            cancellationMotive: $data['cancellation_motive'],
            cancelledBy: $request->user()?->name ?? 'system',
        );

        return response()->json($result);
    }
}
