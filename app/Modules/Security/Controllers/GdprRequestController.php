<?php

namespace App\Modules\Security\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Security\Models\GdprRequest;
use App\Modules\Security\Services\GdprRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * RF-110 — API de gestión derechos Habeas Data (Ley 1581/2012).
 *
 * @see DOCUMENTO_RECTOR §14.3
 */
final class GdprRequestController extends Controller
{
    public function __construct(
        private readonly GdprRequestService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = GdprRequest::query()->with(['affiliate.person', 'requester', 'resolver']);

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->has('affiliate_id')) {
            $query->where('affiliate_id', $request->input('affiliate_id'));
        }

        return response()->json($query->orderByDesc('created_at')->paginate(20));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'affiliate_id' => ['required', 'integer', 'exists:afl_affiliates,id'],
            'type' => ['required', 'string', Rule::in(GdprRequestService::TYPES)],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $gdprRequest = $this->service->create(
            (int) $data['affiliate_id'],
            $data['type'],
            $data['description'] ?? null,
        );

        return response()->json($gdprRequest->load('affiliate.person'), 201);
    }

    public function show(GdprRequest $gdprRequest): JsonResponse
    {
        return response()->json(
            $gdprRequest->load(['affiliate.person', 'requester', 'resolver'])
        );
    }

    public function resolve(Request $request, GdprRequest $gdprRequest): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'string', Rule::in(['RESUELTA', 'RECHAZADA'])],
            'resolution_notes' => ['required', 'string', 'max:2000'],
        ]);

        $resolved = $this->service->resolve($gdprRequest, $data['status'], $data['resolution_notes']);

        return response()->json($resolved->load(['affiliate.person', 'requester', 'resolver']));
    }

    public function summary(): JsonResponse
    {
        return response()->json($this->service->summary());
    }
}
