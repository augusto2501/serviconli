<?php

namespace Database\Seeders;

use App\Modules\RegulatoryEngine\Models\RegulatoryParameter;
use Illuminate\Database\Seeder;

/**
 * Valores vigentes 2026 (Colombia): SMMLV y auxilio según decretales oficiales.
 * Tasas de aporte SS: revisar resoluciones UPC / normativa aplicable al período.
 */
class RegulatoryParameterSeeder extends Seeder
{
    public function run(): void
    {
        $from = '2026-01-01';

        $rows = [
            ['monetary', 'SMMLV', '1750905', 'integer', 'Decreto 0159 de 19 feb 2026 (fijación transitoria SMMLV); marco Decreto 1469/2025', $from],
            ['monetary', 'AUXILIO_TRANSPORTE', '249095', 'integer', 'Auxilio transporte mensual 2026 (no salarial); mismo marco decretales SMMLV 2026', $from],
            ['rates', 'SALUD_TOTAL_PERCENT', '12.5', 'decimal', 'Total aporte salud', $from],
            ['rates', 'SALUD_EMPLOYER_PERCENT', '8.5', 'decimal', 'Parte empleador salud', $from],
            ['rates', 'SALUD_EMPLOYEE_PERCENT', '4', 'decimal', 'Parte trabajador salud', $from],
            ['rates', 'PENSION_TOTAL_PERCENT', '16', 'decimal', 'Total pensión', $from],
            ['rates', 'PENSION_EMPLOYER_PERCENT', '12', 'decimal', 'Parte empleador pensión', $from],
            ['rates', 'PENSION_EMPLOYEE_PERCENT', '4', 'decimal', 'Parte trabajador pensión', $from],
            ['rates', 'ARL_RISK_CLASS_I_PERCENT', '0.522', 'decimal', 'ARL clase I', $from],
            ['rates', 'ARL_RISK_CLASS_II_PERCENT', '1.044', 'decimal', 'ARL clase II', $from],
            ['rates', 'ARL_RISK_CLASS_III_PERCENT', '2.436', 'decimal', 'ARL clase III', $from],
            ['rates', 'ARL_RISK_CLASS_IV_PERCENT', '4.350', 'decimal', 'ARL clase IV', $from],
            ['rates', 'ARL_RISK_CLASS_V_PERCENT', '6.960', 'decimal', 'ARL clase V', $from],
            ['rates', 'CCF_DEPENDIENTE_PERCENT', '4', 'decimal', 'CCF dependiente', $from],
            ['rates', 'CCF_INDEPENDIENTE_PERCENT', '2', 'decimal', 'CCF independiente', $from],
            ['ibc', 'MIN_SMMLV_UNITS', '1', 'decimal', 'IBC mínimo en SMMLV', $from],
            ['ibc', 'MAX_SMMLV_UNITS', '25', 'decimal', 'IBC máximo en SMMLV', $from],
            ['mora', 'DAILY_RATE_PERCENT', '0.0833', 'decimal', 'Interés mora diario (~2.5%/mes)', $from],
        ];

        foreach ($rows as [$category, $key, $value, $dataType, $legal, $validFrom]) {
            RegulatoryParameter::query()->updateOrCreate(
                [
                    'category' => $category,
                    'key' => $key,
                    'valid_from' => $validFrom,
                ],
                [
                    'value' => $value,
                    'data_type' => $dataType,
                    'legal_basis' => $legal,
                    'valid_until' => null,
                ]
            );
        }
    }
}
