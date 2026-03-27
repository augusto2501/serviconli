<?php

namespace App\Modules\Affiliates\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Affiliates\Models\Affiliate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AffiliateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(100, max(1, (int) $request->input('per_page', 15)));

        $query = Affiliate::query()->orderBy('document_number');

        if ($request->filled('q')) {
            $term = '%'.$request->string('q')->trim().'%';
            $query->where(function ($w) use ($term): void {
                $w->where('document_number', 'like', $term)
                    ->orWhere('first_name', 'like', $term)
                    ->orWhere('last_name', 'like', $term);
            });
        }

        $paginator = $query->paginate($perPage);

        return response()->json([
            'data' => collect($paginator->items())->map(fn (Affiliate $a): array => $this->affiliateToArray($a))->values()->all(),
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
            'document_number' => ['required', 'string', 'max:32', 'unique:affiliates,document_number'],
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
        ]);

        $affiliate = Affiliate::query()->create($validated);

        return response()->json($this->affiliateToArray($affiliate), 201);
    }

    public function show(Affiliate $affiliate): JsonResponse
    {
        return response()->json($this->affiliateToArray($affiliate));
    }

    public function update(Request $request, Affiliate $affiliate): JsonResponse
    {
        $validated = $request->validate([
            'document_number' => ['sometimes', 'required', 'string', 'max:32', 'unique:affiliates,document_number,'.$affiliate->id],
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
        ]);

        $affiliate->update($validated);

        return response()->json($this->affiliateToArray($affiliate->fresh()));
    }

    public function destroy(Affiliate $affiliate): JsonResponse
    {
        $affiliate->delete();

        return response()->json(null, 204);
    }

    /** @return array<string, mixed> */
    private function affiliateToArray(Affiliate $a): array
    {
        return [
            'id' => $a->id,
            'documentNumber' => $a->document_number,
            'firstName' => $a->first_name,
            'lastName' => $a->last_name,
            'createdAt' => $a->created_at?->toIso8601String(),
            'updatedAt' => $a->updated_at?->toIso8601String(),
        ];
    }
}
