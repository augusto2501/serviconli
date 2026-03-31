<?php

namespace App\Modules\PILALiquidation\Services;

use App\Modules\PILALiquidation\Models\LiquidationBatch;

/**
 * Formateador XLSX para planilla PILA — 42 columnas.
 *
 * Genera un CSV (compatible con Excel) con las 42 columnas estándar.
 *
 * @see DOCUMENTO_RECTOR §6 Flujo 8, RN-21
 */
final class XLSXFileFormatter
{
    private readonly PILACharNormalizer $norm;

    private const HEADERS = [
        'TIPO_DOC',
        'NUM_DOC',
        'PRIMER_APELLIDO',
        'SEGUNDO_APELLIDO',
        'PRIMER_NOMBRE',
        'SEGUNDO_NOMBRE',
        'TIPO_COTIZANTE',
        'SUBTIPO',
        'NOV_ING',
        'NOV_RET',
        'NOV_TAE',
        'NOV_TAP',
        'NOV_VSP',
        'NOV_VST',
        'NOV_SLN',
        'NOV_IGE',
        'NOV_LMA',
        'NOV_VAC',
        'COD_EPS',
        'COD_AFP',
        'COD_ARL',
        'COD_CCF',
        'DIAS_EPS',
        'DIAS_AFP',
        'DIAS_ARL',
        'DIAS_CCF',
        'SALARIO',
        'IBC_EPS',
        'IBC_AFP',
        'IBC_ARL',
        'IBC_CCF',
        'TARIFA_EPS',
        'APORTE_EPS',
        'TARIFA_AFP',
        'APORTE_AFP',
        'SOLIDARIDAD',
        'TARIFA_ARL',
        'APORTE_ARL',
        'TARIFA_CCF',
        'APORTE_CCF',
        'TOTAL_SS',
        'TOTAL_PAGAR',
    ];

    public function __construct()
    {
        $this->norm = new PILACharNormalizer;
    }

    /**
     * Genera contenido CSV con 42 columnas.
     */
    public function generate(LiquidationBatch $batch): string
    {
        $batch->loadMissing([
            'lines.affiliate.person',
            'lines.affiliate.currentSocialSecurityProfile.epsEntity',
            'lines.affiliate.currentSocialSecurityProfile.afpEntity',
            'lines.affiliate.currentSocialSecurityProfile.arlEntity',
            'lines.affiliate.currentSocialSecurityProfile.ccfEntity',
        ]);

        $output = fopen('php://temp', 'r+');
        fputcsv($output, self::HEADERS, ';');

        $included = $batch->lines->where('line_status', 'INCLUIDO');
        foreach ($included as $line) {
            $person = $line->affiliate?->person;
            $ssProfile = $line->affiliate?->currentSocialSecurityProfile;
            $n = $this->norm;
            $novelties = $line->novelties ?? [];
            $hasNovelty = fn (string $code) => collect($novelties)->contains(fn ($nv) => ($nv['type_code'] ?? '') === $code);

            fputcsv($output, [
                $person?->document_type ?? 'CC',
                $person?->document_number ?? '',
                $n->normalize($person?->first_surname ?? ''),
                $n->normalize($person?->second_surname ?? ''),
                $n->normalize($person?->first_name ?? ''),
                $n->normalize($person?->second_name ?? ''),
                $line->contributor_type_code ?? '01',
                $line->subtipo ?? 0,
                $hasNovelty('ING') ? 'X' : '',
                $hasNovelty('RET') ? 'X' : '',
                $hasNovelty('TAE') ? 'X' : '',
                $hasNovelty('TAP') ? 'X' : '',
                $hasNovelty('VSP') ? 'X' : '',
                $hasNovelty('VST') ? 'X' : '',
                $hasNovelty('SLN') ? 'X' : '',
                $hasNovelty('IGE') ? 'X' : '',
                $hasNovelty('LMA') ? 'X' : '',
                $hasNovelty('VAC') ? 'X' : '',
                $ssProfile?->epsEntity?->pila_code ?? '',
                $ssProfile?->afpEntity?->pila_code ?? '',
                $ssProfile?->arlEntity?->pila_code ?? '',
                $ssProfile?->ccfEntity?->pila_code ?? '',
                $line->days_eps ?? 30,
                $line->days_afp ?? 30,
                $line->days_arl ?? 30,
                $line->days_ccf ?? 30,
                $line->salary ?? 0,
                $line->ibc ?? 0,
                $line->ibc2 ?? $line->ibc ?? 0,
                $line->ibc ?? 0,
                $line->ibc ?? 0,
                number_format((float) ($ssProfile?->eps_tarifa ?? 12.5), 5, '.', ''),
                $line->health_total ?? 0,
                number_format((float) ($ssProfile?->afp_tarifa ?? 16.0), 5, '.', ''),
                $line->pension_total ?? 0,
                $line->solidarity ?? 0,
                number_format((float) ($ssProfile?->arl_tarifa ?? 0.522), 5, '.', ''),
                $line->arl_total ?? 0,
                number_format((float) ($ssProfile?->ccf_tarifa ?? 4.0), 5, '.', ''),
                $line->ccf_total ?? 0,
                $line->total_ss ?? 0,
                $line->total_payable ?? 0,
            ], ';');
        }

        rewind($output);
        $content = stream_get_contents($output);
        fclose($output);

        return $content;
    }
}
