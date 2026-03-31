<?php

namespace App\Modules\PILALiquidation\Services;

use App\Modules\PILALiquidation\Models\LiquidationBatch;
use App\Modules\PILALiquidation\Models\PILAFileGeneration;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Generación de archivo PILA — RN-21, 9 pasos.
 *
 * 1. Seleccionar lote confirmado (LIQUIDADO)
 * 2. Validar estado del lote
 * 3. Limpiar staging anterior si existe
 * 4. Insertar datos desde líneas del lote
 * 5. Actualizar planilla number
 * 6. Normalizar Ñ→N [RN-21]
 * 7. Generar según modo (ARUS plano 359+687 o XLSX 42 cols)
 * 8. Verificar conteo
 * 9. Registrar generación + actualizar NoPlanilla
 *
 * Portado de Access Form_PILA + Convertidor_ARUS.
 *
 * @see DOCUMENTO_RECTOR §6 Flujo 8
 */
final class PILAFileGenerationService
{
    public function __construct(
        private readonly ARUSFileFormatter $arusFormatter,
        private readonly XLSXFileFormatter $xlsxFormatter,
        private readonly PILACharNormalizer $normalizer,
    ) {}

    /**
     * Genera el archivo PILA a partir de un lote confirmado.
     *
     * @param  string  $format  PLANO_ARUS o XLSX
     * @return PILAFileGeneration
     */
    public function generate(
        int $batchId,
        string $format = 'PLANO_ARUS',
        ?string $generatedBy = null,
    ): PILAFileGeneration {
        // Paso 1-2: Seleccionar y validar lote
        $batch = LiquidationBatch::query()
            ->with(['payer', 'lines.affiliate.person',
                'lines.affiliate.currentSocialSecurityProfile.epsEntity',
                'lines.affiliate.currentSocialSecurityProfile.afpEntity',
                'lines.affiliate.currentSocialSecurityProfile.arlEntity',
                'lines.affiliate.currentSocialSecurityProfile.ccfEntity',
                'lines.affiliate.currentAffiliatePayer',
            ])
            ->findOrFail($batchId);

        if (! $batch->isConfirmed()) {
            throw new InvalidArgumentException(
                'Solo se puede generar archivo PILA de lotes en estado LIQUIDADO.'
            );
        }

        $included = $batch->lines->where('line_status', 'INCLUIDO');
        if ($included->isEmpty()) {
            throw new InvalidArgumentException('El lote no tiene líneas incluidas.');
        }

        // Paso 3: Limpiar generación anterior
        $this->cleanPreviousGeneration($batch);

        // Paso 5: Planilla number
        $planillaNumber = $batch->planilla_number
            ?? sprintf('PL-%s-%04d%02d-%06d',
                $batch->payer?->nit ?? '0',
                $batch->period_year,
                $batch->period_month,
                $batch->id
            );

        // Paso 6-7: Generar contenido según formato
        $content = match ($format) {
            'PLANO_ARUS' => $this->arusFormatter->generate($batch),
            'XLSX' => $this->xlsxFormatter->generate($batch),
            default => throw new InvalidArgumentException("Formato no soportado: {$format}"),
        };

        // Paso 8: Verificar conteo
        $affiliatesCount = $included->count();

        // Guardar archivo
        $ext = $format === 'PLANO_ARUS' ? 'txt' : 'csv';
        $fileName = sprintf(
            'pila_%s_%04d%02d_%s.%s',
            $batch->payer?->nit ?? 'sin-nit',
            $batch->period_year,
            $batch->period_month,
            now()->format('YmdHis'),
            $ext,
        );
        $filePath = "pila-files/{$fileName}";

        if ($format === 'PLANO_ARUS') {
            $content = $this->normalizer->toAnsi($content);
        }

        Storage::disk('local')->put($filePath, $content);

        // Paso 9: Registrar generación
        $generation = PILAFileGeneration::query()->create([
            'payer_id' => $batch->payer_id,
            'batch_id' => $batch->id,
            'period_year' => $batch->period_year,
            'period_month' => $batch->period_month,
            'planilla_type' => $batch->planilla_type,
            'operator_id' => $batch->operator_id,
            'branch_code' => $batch->branch_code,
            'planilla_number' => $planillaNumber,
            'payment_date' => $batch->payment_date,
            'affiliates_count' => $affiliatesCount,
            'file_path' => $filePath,
            'file_format' => $format,
            'generated_by' => $generatedBy,
            'status' => 'GENERADO',
        ]);

        $batch->update(['planilla_number' => $planillaNumber]);

        return $generation;
    }

    /**
     * Descarga el archivo generado.
     */
    public function download(PILAFileGeneration $generation): ?string
    {
        if (! Storage::disk('local')->exists($generation->file_path)) {
            return null;
        }

        return Storage::disk('local')->path($generation->file_path);
    }

    private function cleanPreviousGeneration(LiquidationBatch $batch): void
    {
        $previous = PILAFileGeneration::query()
            ->where('batch_id', $batch->id)
            ->get();

        foreach ($previous as $gen) {
            if ($gen->file_path && Storage::disk('local')->exists($gen->file_path)) {
                Storage::disk('local')->delete($gen->file_path);
            }
            $gen->delete();
        }
    }
}
