<?php

namespace App\Modules\Advisors\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Advisors\Models\Advisor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AdvisorController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Advisor::class, 'advisor');
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = min(100, max(1, (int) $request->input('per_page', 15)));

        $paginator = Advisor::query()
            ->orderBy('code')
            ->paginate($perPage);

        return response()->json([
            'data' => collect($paginator->items())->map(fn (Advisor $a): array => $this->toArray($a))->values()->all(),
            'meta' => [
                'currentPage' => $paginator->currentPage(),
                'lastPage' => $paginator->lastPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:32', 'unique:sec_advisors,code'],
            'document_type' => ['nullable', 'string', 'max:8'],
            'document_number' => ['nullable', 'string', 'max:32'],
            'first_name' => ['required', 'string', 'max:128'],
            'last_name' => ['nullable', 'string', 'max:128'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'commission_new' => ['required', 'integer', 'min:0', 'max:999999999999'],
            'commission_recurring' => ['required', 'integer', 'min:0', 'max:999999999999'],
            'authorizes_credits' => ['sometimes', 'boolean'],
        ]);

        $advisor = Advisor::query()->create([
            ...$validated,
            'authorizes_credits' => (bool) ($validated['authorizes_credits'] ?? false),
        ]);

        return response()->json($this->toArray($advisor), 201);
    }

    public function show(Advisor $advisor): JsonResponse
    {
        return response()->json($this->toArray($advisor));
    }

    public function update(Request $request, Advisor $advisor): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['sometimes', 'string', 'max:32', 'unique:sec_advisors,code,'.$advisor->id],
            'document_type' => ['nullable', 'string', 'max:8'],
            'document_number' => ['nullable', 'string', 'max:32'],
            'first_name' => ['sometimes', 'string', 'max:128'],
            'last_name' => ['nullable', 'string', 'max:128'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'commission_new' => ['sometimes', 'integer', 'min:0', 'max:999999999999'],
            'commission_recurring' => ['sometimes', 'integer', 'min:0', 'max:999999999999'],
            'authorizes_credits' => ['sometimes', 'boolean'],
        ]);

        $advisor->fill($validated);
        $advisor->save();

        return response()->json($this->toArray($advisor->fresh()));
    }

    public function destroy(Advisor $advisor): JsonResponse
    {
        $advisor->delete();

        return response()->json(null, 204);
    }

    /** @return array<string, mixed> */
    private function toArray(Advisor $advisor): array
    {
        return [
            'id' => $advisor->id,
            'code' => $advisor->code,
            'documentType' => $advisor->document_type,
            'documentNumber' => $advisor->document_number,
            'firstName' => $advisor->first_name,
            'lastName' => $advisor->last_name,
            'phone' => $advisor->phone,
            'email' => $advisor->email,
            'commissionNew' => (int) $advisor->commission_new,
            'commissionRecurring' => (int) $advisor->commission_recurring,
            'authorizesCredits' => (bool) $advisor->authorizes_credits,
        ];
    }
}
