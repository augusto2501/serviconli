<?php

namespace App\Modules\PILALiquidation\Services;

use App\Modules\Affiliations\Models\Payer;
use App\Modules\PILALiquidation\Models\LiquidationBatch;
use App\Modules\PILALiquidation\Models\LiquidationBatchLine;

/**
 * Formateador de archivo plano ARUS — RN-21.
 *
 * Registro tipo 01: encabezado (359 caracteres).
 * Registro tipo 02: detalle por afiliado (687 caracteres, 113 campos).
 *
 * Portado de Access Convertidor_ARUS FormatTipo01/FormatTipo02.
 *
 * @see DOCUMENTO_RECTOR §6 Flujo 8
 */
final class ARUSFileFormatter
{
    private readonly PILACharNormalizer $norm;

    public function __construct()
    {
        $this->norm = new PILACharNormalizer;
    }

    /**
     * Genera contenido completo del archivo plano.
     *
     * @return string Contenido en formato ARUS
     */
    public function generate(LiquidationBatch $batch): string
    {
        $batch->loadMissing([
            'payer',
            'lines.affiliate.person',
            'lines.affiliate.currentSocialSecurityProfile.epsEntity',
            'lines.affiliate.currentSocialSecurityProfile.afpEntity',
            'lines.affiliate.currentSocialSecurityProfile.arlEntity',
            'lines.affiliate.currentSocialSecurityProfile.ccfEntity',
            'lines.affiliate.currentAffiliatePayer',
        ]);

        $lines = [];
        $lines[] = $this->formatTipo01($batch);

        $included = $batch->lines->where('line_status', 'INCLUIDO');
        foreach ($included as $batchLine) {
            $lines[] = $this->formatTipo02($batch, $batchLine);
        }

        return implode("\r\n", $lines) . "\r\n";
    }

    /**
     * Registro tipo 01 — Encabezado del operador (359 chars).
     *
     * Portado de Access Convertidor_ARUS FormatTipo01.
     */
    private function formatTipo01(LiquidationBatch $batch): string
    {
        $payer = $batch->payer;
        $n = $this->norm;

        $record = '01';                                                    // 1-2: Tipo registro
        $record .= '0001';                                                // 3-6: Modalidad planilla (0001)
        $record .= $n->padZero(0, 4);                                    // 7-10: Secuencia
        $record .= $n->padRight($payer?->razon_social ?? '', 200);        // 11-210: Razón social aportante
        $record .= $payer ? 'NI' : '  ';                                 // 211-212: Tipo documento aportante
        $record .= $n->padZero($payer?->nit ?? '', 16);                  // 213-228: Número documento
        $record .= $n->padZero($payer?->digito_verificacion ?? 0, 1);    // 229: Dígito verificación
        $record .= $n->padRight($batch->planilla_type ?? 'E', 1);       // 230: Tipo planilla E/Y/A/S/I
        $record .= $n->padZero($batch->period_year, 4);                  // 231-234: Año cotización
        $record .= $n->padZero($batch->period_month, 2);                 // 235-236: Mes cotización
        $record .= $n->padRight('', 30);                                  // 237-266: Forma presentación
        $record .= $n->padRight($payer?->pila_operator_code ?? '', 10);  // 267-276: Código sucursal
        $record .= $n->padRight($batch->branch_code ?? '', 10);         // 277-286: Código sucursal ARUS
        $record .= $n->padZero($batch->cant_affiliates, 5);             // 287-291: Número cotizantes
        $record .= $n->padZero($batch->grand_total, 12);                // 292-303: Valor planilla
        $record .= $n->padRight($batch->payment_date?->format('Y-m-d') ?? '', 10); // 304-313: Fecha pago
        $record .= $n->padZero($batch->planilla_number ?? '', 10);      // 314-323: Número planilla
        $record .= $n->padRight('', 36);                                  // 324-359: Reservado

        return substr(str_pad($record, 359), 0, 359);
    }

    /**
     * Registro tipo 02 — Detalle por afiliado (687 chars, 113 campos).
     *
     * Portado de Access Convertidor_ARUS FormatTipo02.
     * Solo los campos más relevantes — los demás van con padding.
     */
    private function formatTipo02(LiquidationBatch $batch, LiquidationBatchLine $line): string
    {
        $affiliate = $line->affiliate;
        $person = $affiliate?->person;
        $ssProfile = $affiliate?->currentSocialSecurityProfile;
        $link = $affiliate?->currentAffiliatePayer;
        $n = $this->norm;

        $record = '02';                                                       // 1-2: Tipo registro
        $record .= '0001';                                                   // 3-6: Secuencia
        $record .= $n->padRight($person?->document_type ?? 'CC', 2);        // 7-8: Tipo doc cotizante
        $record .= $n->padRight($person?->document_number ?? '', 16);       // 9-24: Número documento
        $record .= $n->padZero($line->contributor_type_code ?? '01', 2);    // 25-26: Tipo cotizante
        $record .= $n->padZero($line->subtipo ?? 0, 2);                     // 27-28: Subtipo cotizante
        $record .= $this->noveltyFlag($line, 'ING');                         // 29: Novedad ingreso
        $record .= $this->noveltyFlag($line, 'RET');                         // 30: Novedad retiro
        $record .= $this->noveltyFlag($line, 'TAE');                         // 31: Traslado EPS
        $record .= $this->noveltyFlag($line, 'TAP');                         // 32: Traslado AFP
        $record .= $this->noveltyFlag($line, 'VSP');                         // 33: Variación salario perm.
        $record .= $this->noveltyFlag($line, 'COR');                         // 34: Corrección
        $record .= $this->noveltyFlag($line, 'VST');                         // 35: Variación salario temp.
        $record .= $this->noveltyFlag($line, 'SLN');                         // 36: SLN
        $record .= $this->noveltyFlag($line, 'IGE');                         // 37: IGE
        $record .= $this->noveltyFlag($line, 'LMA');                         // 38: LMA
        $record .= $this->noveltyFlag($line, 'VAC');                         // 39: Vacaciones
        $record .= $this->noveltyFlag($line, 'AVP');                         // 40: AVP
        $record .= $this->noveltyFlag($line, 'VCT');                         // 41: VCT
        $record .= '  ';                                                      // 42-43: IRL (reservado)
        $record .= $n->padRight($ssProfile?->epsEntity?->pila_code ?? '', 6);  // 44-49: Código EPS
        $record .= $n->padRight($ssProfile?->afpEntity?->pila_code ?? '', 6);  // 50-55: Código AFP
        $record .= $n->padRight($ssProfile?->arlEntity?->pila_code ?? '', 6);  // 56-61: Código ARL
        $record .= $n->padRight($ssProfile?->ccfEntity?->pila_code ?? '', 6);  // 62-67: Código CCF
        $record .= $n->padZero($line->days_eps ?? 30, 2);                     // 68-69: Días EPS
        $record .= $n->padZero($line->days_afp ?? 30, 2);                     // 70-71: Días AFP
        $record .= $n->padZero($line->days_arl ?? 30, 2);                     // 72-73: Días ARL
        $record .= $n->padZero($line->days_ccf ?? 30, 2);                     // 74-75: Días CCF
        $record .= $n->padZero($line->salary ?? 0, 9);                        // 76-84: Salario básico
        $record .= ' ';                                                        // 85: Integral (S/N)
        $record .= $n->padZero($line->ibc ?? 0, 9);                           // 86-94: IBC EPS
        $record .= $n->padZero($line->ibc2 ?? $line->ibc ?? 0, 9);           // 95-103: IBC AFP
        $record .= $n->padZero($line->ibc ?? 0, 9);                           // 104-112: IBC ARL
        $record .= $n->padZero($line->ibc ?? 0, 9);                           // 113-121: IBC CCF

        // Tarifas (6 decimales con 7 chars en formato porcentaje)
        $record .= $this->formatRate($ssProfile?->eps_tarifa ?? 12.5);       // 122-128: Tarifa EPS
        $record .= $n->padZero($line->health_total ?? 0, 9);                // 129-137: Aporte EPS
        $record .= $this->formatRate($ssProfile?->afp_tarifa ?? 16.0);      // 138-144: Tarifa AFP
        $record .= $n->padZero($line->pension_total ?? 0, 9);               // 145-153: Aporte AFP
        $record .= $n->padZero($line->solidarity ?? 0, 9);                  // 154-162: Fondo solidaridad
        $record .= $n->padZero(0, 9);                                        // 163-171: Fondo subsistencia
        $record .= $n->padZero(0, 9);                                        // 172-180: Valor no retenido
        $record .= $this->formatRate($ssProfile?->arl_tarifa ?? 0.522);     // 181-187: Tarifa ARL
        $record .= $n->padZero(0, 2);                                        // 188-189: Centro trabajo
        $record .= $n->padZero($line->arl_total ?? 0, 9);                   // 190-198: Aporte ARL
        $record .= $this->formatRate($ssProfile?->ccf_tarifa ?? 4.0);       // 199-205: Tarifa CCF
        $record .= $n->padZero($line->ccf_total ?? 0, 9);                   // 206-214: Aporte CCF

        // Campos restantes (padding hasta 687)
        $remaining = 687 - strlen($record);
        if ($remaining > 0) {
            $record .= str_repeat(' ', $remaining);
        }

        // Datos de persona al final (nombre)
        $nameStart = 580;
        $firstName = $n->padRight($person?->first_name ?? '', 20);
        $secondName = $n->padRight($person?->second_name ?? '', 30);
        $firstSurname = $n->padRight($person?->first_surname ?? '', 20);
        $secondSurname = $n->padRight($person?->second_surname ?? '', 30);
        $nameBlock = $firstSurname . $secondSurname . $firstName . $secondName;

        $record = substr($record, 0, $nameStart) . $nameBlock .
                  substr($record, min($nameStart + strlen($nameBlock), 687));

        return substr(str_pad($record, 687), 0, 687);
    }

    private function noveltyFlag(LiquidationBatchLine $line, string $code): string
    {
        $novelties = $line->novelties ?? [];
        foreach ($novelties as $n) {
            if (($n['type_code'] ?? '') === $code) {
                return 'X';
            }
        }

        return ' ';
    }

    private function formatRate(float|string|null $rate): string
    {
        $value = (float) ($rate ?? 0);

        return str_pad(str_replace('.', '', number_format($value, 5, '.', '')), 7, '0', STR_PAD_LEFT);
    }
}
