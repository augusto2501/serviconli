<?php

namespace App\Modules\PILALiquidation\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\PILALiquidation\Models\PILAFileGeneration;
use App\Modules\PILALiquidation\Services\PILAFileGenerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * API para Flujo 8 — Generación Archivo PILA.
 *
 * @see DOCUMENTO_RECTOR §6 Flujo 8, RN-21
 */
final class PILAFileController extends Controller
{
    /**
     * Genera archivo PILA desde un lote confirmado.
     */
    public function generate(Request $request, PILAFileGenerationService $service): JsonResponse
    {
        $validated = $request->validate([
            'batch_id' => ['required', 'integer', 'exists:pay_liquidation_batches,id'],
            'format' => ['required', 'string', 'in:PLANO_ARUS,XLSX'],
        ]);

        try {
            $generation = $service->generate(
                (int) $validated['batch_id'],
                $validated['format'],
                $request->user()?->email,
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'id' => $generation->id,
            'file_path' => $generation->file_path,
            'file_format' => $generation->file_format,
            'affiliates_count' => $generation->affiliates_count,
            'planilla_number' => $generation->planilla_number,
            'status' => $generation->status,
            'created_at' => $generation->created_at?->toIso8601String(),
        ], 201);
    }

    /**
     * Lista generaciones PILA.
     */
    public function index(Request $request): JsonResponse
    {
        $query = PILAFileGeneration::query()
            ->with('payer')
            ->orderByDesc('id');

        if ($request->has('batch_id')) {
            $query->where('batch_id', (int) $request->input('batch_id'));
        }

        $items = $query->paginate(15);

        return response()->json([
            'data' => collect($items->items())->map(fn ($g) => [
                'id' => $g->id,
                'payer_name' => $g->payer?->razon_social,
                'period' => "{$g->period_year}-".str_pad($g->period_month, 2, '0', STR_PAD_LEFT),
                'file_format' => $g->file_format,
                'affiliates_count' => $g->affiliates_count,
                'planilla_number' => $g->planilla_number,
                'status' => $g->status,
                'created_at' => $g->created_at?->toIso8601String(),
            ]),
            'meta' => [
                'total' => $items->total(),
                'currentPage' => $items->currentPage(),
                'lastPage' => $items->lastPage(),
            ],
        ]);
    }

    /**
     * Descarga el archivo generado.
     */
    public function download(int $generationId, PILAFileGenerationService $service): BinaryFileResponse|JsonResponse
    {
        $generation = PILAFileGeneration::query()->findOrFail($generationId);
        $path = $service->download($generation);

        if ($path === null) {
            return response()->json(['message' => 'Archivo no encontrado.'], 404);
        }

        $ext = $generation->file_format === 'PLANO_ARUS' ? 'txt' : 'csv';
        $downloadName = "planilla_pila_{$generation->planilla_number}.{$ext}";

        return response()->download($path, $downloadName);
    }
}
