<?php

namespace App\Modules\Employers\Controllers;

// RF-024–RF-027 — CRUD empleadores con validación NIT (módulo 11)

use App\Http\Controllers\Controller;
use App\Modules\Employers\Models\Employer;
use App\Modules\Employers\Services\EmployerNitValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class EmployerController extends Controller
{
    public function __construct(
        private readonly EmployerNitValidationService $nitValidation,
    ) {
        $this->authorizeResource(Employer::class, 'employer');
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = min(100, max(1, (int) $request->input('per_page', 15)));

        $query = Employer::query()->orderBy('razon_social');

        if ($request->filled('q')) {
            $term = '%'.$request->string('q')->trim().'%';
            $query->where(function ($w) use ($term): void {
                $w->where('nit_body', 'like', $term)
                    ->orWhere('razon_social', 'like', $term);
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        $paginator = $query->paginate($perPage);

        return response()->json([
            'data' => collect($paginator->items())->map(fn (Employer $e): array => $this->toArray($e))->values()->all(),
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
            'nit_body' => ['required', 'string', 'max:16'],
            'digito_verificacion' => ['required', 'integer', 'min:0', 'max:9'],
            'razon_social' => ['required', 'string', 'max:255'],
            'nombre_corto' => ['nullable', 'string', 'max:255'],
            'representante_legal' => ['nullable', 'string', 'max:255'],
            'representante_documento' => ['nullable', 'string', 'max:32'],
            'tipo_persona' => ['nullable', 'string', 'max:32'],
            'naturaleza_juridica' => ['nullable', 'string', 'max:64'],
            'actividad_economica_code' => ['nullable', 'string', 'max:32'],
            'address' => ['nullable', 'string', 'max:255'],
            'city_name' => ['nullable', 'string', 'max:255'],
            'department_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'status' => ['nullable', 'string', 'max:32'],
        ]);

        try {
            $nit = $this->nitValidation->assertValid($validated);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        if (Employer::query()
            ->where('nit_body', $nit['nit_body'])
            ->where('digito_verificacion', $nit['digito_verificacion'])
            ->exists()) {
            return response()->json(['message' => 'Ya existe un empleador con este NIT.'], 422);
        }

        $employer = Employer::query()->create([
            ...$nit,
            'nombre_corto' => $validated['nombre_corto'] ?? null,
            'representante_legal' => $validated['representante_legal'] ?? null,
            'representante_documento' => $validated['representante_documento'] ?? null,
            'tipo_persona' => $validated['tipo_persona'] ?? null,
            'naturaleza_juridica' => $validated['naturaleza_juridica'] ?? null,
            'actividad_economica_code' => $validated['actividad_economica_code'] ?? null,
            'address' => $validated['address'] ?? null,
            'city_name' => $validated['city_name'] ?? null,
            'department_name' => $validated['department_name'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'status' => $validated['status'] ?? 'ACTIVE',
        ]);

        return response()->json($this->toArray($employer), 201);
    }

    public function show(Employer $employer): JsonResponse
    {
        return response()->json($this->toArray($employer));
    }

    public function update(Request $request, Employer $employer): JsonResponse
    {
        $validated = $request->validate([
            'nit_body' => ['sometimes', 'required', 'string', 'max:16'],
            'digito_verificacion' => ['sometimes', 'required', 'integer', 'min:0', 'max:9'],
            'razon_social' => ['sometimes', 'required', 'string', 'max:255'],
            'nombre_corto' => ['nullable', 'string', 'max:255'],
            'representante_legal' => ['nullable', 'string', 'max:255'],
            'representante_documento' => ['nullable', 'string', 'max:32'],
            'tipo_persona' => ['nullable', 'string', 'max:32'],
            'naturaleza_juridica' => ['nullable', 'string', 'max:64'],
            'actividad_economica_code' => ['nullable', 'string', 'max:32'],
            'address' => ['nullable', 'string', 'max:255'],
            'city_name' => ['nullable', 'string', 'max:255'],
            'department_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'status' => ['nullable', 'string', 'max:32'],
        ]);

        $nitBody = $validated['nit_body'] ?? $employer->nit_body;
        $dv = isset($validated['digito_verificacion'])
            ? (int) $validated['digito_verificacion']
            : (int) $employer->digito_verificacion;

        try {
            $nit = $this->nitValidation->assertValid([
                'nit_body' => $nitBody,
                'digito_verificacion' => $dv,
                'razon_social' => $validated['razon_social'] ?? $employer->razon_social,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        if (Employer::query()
            ->where('nit_body', $nit['nit_body'])
            ->where('digito_verificacion', $nit['digito_verificacion'])
            ->where('id', '!=', $employer->id)
            ->exists()) {
            return response()->json(['message' => 'Ya existe otro empleador con este NIT.'], 422);
        }

        $employer->update([
            'nit_body' => $nit['nit_body'],
            'digito_verificacion' => $nit['digito_verificacion'],
            'razon_social' => $nit['razon_social'],
            'nombre_corto' => $validated['nombre_corto'] ?? $employer->nombre_corto,
            'representante_legal' => $validated['representante_legal'] ?? $employer->representante_legal,
            'representante_documento' => $validated['representante_documento'] ?? $employer->representante_documento,
            'tipo_persona' => $validated['tipo_persona'] ?? $employer->tipo_persona,
            'naturaleza_juridica' => $validated['naturaleza_juridica'] ?? $employer->naturaleza_juridica,
            'actividad_economica_code' => $validated['actividad_economica_code'] ?? $employer->actividad_economica_code,
            'address' => $validated['address'] ?? $employer->address,
            'city_name' => $validated['city_name'] ?? $employer->city_name,
            'department_name' => $validated['department_name'] ?? $employer->department_name,
            'phone' => $validated['phone'] ?? $employer->phone,
            'email' => $validated['email'] ?? $employer->email,
            'status' => $validated['status'] ?? $employer->status,
        ]);

        return response()->json($this->toArray($employer->fresh()));
    }

    public function destroy(Employer $employer): JsonResponse
    {
        $employer->delete();

        return response()->json(null, 204);
    }

    /** @return array<string, mixed> */
    private function toArray(Employer $e): array
    {
        return [
            'id' => $e->id,
            'nitBody' => $e->nit_body,
            'digitoVerificacion' => $e->digito_verificacion,
            'razonSocial' => $e->razon_social,
            'nombreCorto' => $e->nombre_corto,
            'representanteLegal' => $e->representante_legal,
            'representanteDocumento' => $e->representante_documento,
            'tipoPersona' => $e->tipo_persona,
            'naturalezaJuridica' => $e->naturaleza_juridica,
            'actividadEconomicaCode' => $e->actividad_economica_code,
            'address' => $e->address,
            'cityName' => $e->city_name,
            'departmentName' => $e->department_name,
            'phone' => $e->phone,
            'email' => $e->email,
            'status' => $e->status,
            'createdAt' => $e->created_at?->toIso8601String(),
            'updatedAt' => $e->updated_at?->toIso8601String(),
        ];
    }
}
