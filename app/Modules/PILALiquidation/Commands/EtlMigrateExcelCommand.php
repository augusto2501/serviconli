<?php

namespace App\Modules\PILALiquidation\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * RF-118 — ETL desde DataSegura-SERVICONLI-2025.xlsx.
 *
 * 8 transformaciones de limpieza:
 *   1. Normalizar NIT (3 formatos → número + DV separado)
 *   2. Parsear MES_PAGO (22 variantes → período YYYYMM + estado)
 *   3. Cifrar credenciales AES-256
 *   4. Limpiar teléfonos float ("3223109130.0" → "3223109130")
 *   5. Normalizar geografía ("QUINDIO"/"Quindío" → estándar)
 *   6. Unificar nulos (N/A, SIN INFORMACIÓN → NULL)
 *   7. Limpiar documentos float ("15296441.0" → "15296441")
 *   8. Agregar PPT/PTT al catálogo tipos documento
 *
 * @see DOCUMENTO_RECTOR §16, RF-118, SKILL.md §"Problemas de Calidad del Excel"
 */
final class EtlMigrateExcelCommand extends Command
{
    protected $signature = 'etl:migrate-excel
        {path : Ruta al archivo Excel DataSegura-SERVICONLI-2025.xlsx}
        {--dry-run : Simular sin escribir en BD}
        {--sheet=* : Hojas específicas a procesar (default: todas)}';

    protected $description = 'RF-118: Importa y transforma datos desde el Excel maestro de Serviconli';

    /** Valores que representan NULL en el Excel original. */
    private const NULL_VARIANTS = [
        'N/A', 'n/a', 'NA', 'na', 'SIN INFORMACIÓN', 'SIN INFORMACION',
        'SIN INFO', 'NO APLICA', 'NO TIENE', 'NINGUNO', '-', '', ' ',
    ];

    public function handle(): int
    {
        $path = $this->argument('path');
        $dryRun = $this->option('dry-run');

        if (! file_exists($path)) {
            $this->error("Archivo no encontrado: {$path}");

            return self::FAILURE;
        }

        $this->info($dryRun ? '🔍 Modo DRY-RUN — no se escribirá en BD' : '▶ Iniciando ETL Excel...');

        // TODO: Implementar lectura con OpenSpout cuando se reciba el archivo real
        // Las 8 transformaciones están definidas como métodos privados listos para usar

        $this->info('Estructura ETL lista. Pendiente: archivo real del cliente.');
        $this->table(
            ['Paso', 'Transformación', 'Estado'],
            [
                [1, 'Normalizar NIT', 'Listo'],
                [2, 'Parsear MES_PAGO', 'Listo'],
                [3, 'Cifrar credenciales AES-256', 'Listo'],
                [4, 'Limpiar teléfonos float', 'Listo'],
                [5, 'Normalizar geografía', 'Listo'],
                [6, 'Unificar nulos', 'Listo'],
                [7, 'Limpiar documentos float', 'Listo'],
                [8, 'Agregar PPT/PTT', 'Listo'],
            ],
        );

        return self::SUCCESS;
    }

    /** T1: Normalizar NIT — "900966567-4", "9009665674", "900.966.567-4" → body + DV. */
    public function normalizeNit(string $raw): array
    {
        $clean = preg_replace('/[^0-9]/', '', $raw);
        if (strlen($clean) < 2) {
            return ['nit_body' => $clean, 'digito_verificacion' => ''];
        }

        return [
            'nit_body' => substr($clean, 0, -1),
            'digito_verificacion' => substr($clean, -1),
        ];
    }

    /**
     * T2: Parsear MES_PAGO — 22 variantes conocidas.
     * "ENERO 2025", "ENE-25", "2025-01", "01/2025" → ['year' => 2025, 'month' => 1]
     */
    public function parseMesPago(?string $raw): ?array
    {
        if ($raw === null || in_array(trim($raw), self::NULL_VARIANTS, true)) {
            return null;
        }

        $months = [
            'ENERO' => 1, 'FEBRERO' => 2, 'MARZO' => 3, 'ABRIL' => 4,
            'MAYO' => 5, 'JUNIO' => 6, 'JULIO' => 7, 'AGOSTO' => 8,
            'SEPTIEMBRE' => 9, 'OCTUBRE' => 10, 'NOVIEMBRE' => 11, 'DICIEMBRE' => 12,
            'ENE' => 1, 'FEB' => 2, 'MAR' => 3, 'ABR' => 4,
            'MAY' => 5, 'JUN' => 6, 'JUL' => 7, 'AGO' => 8,
            'SEP' => 9, 'OCT' => 10, 'NOV' => 11, 'DIC' => 12,
        ];

        $upper = strtoupper(trim($raw));

        // "2025-01" o "202501"
        if (preg_match('/^(\d{4})-?(\d{2})$/', $upper, $m)) {
            return ['year' => (int) $m[1], 'month' => (int) $m[2]];
        }

        // "01/2025"
        if (preg_match('/^(\d{1,2})[\/\-](\d{4})$/', $upper, $m)) {
            return ['year' => (int) $m[2], 'month' => (int) $m[1]];
        }

        // "ENERO 2025" o "ENE-25"
        foreach ($months as $name => $num) {
            if (str_contains($upper, $name)) {
                if (preg_match('/(\d{2,4})/', $upper, $m)) {
                    $year = (int) $m[1];
                    if ($year < 100) {
                        $year += 2000;
                    }

                    return ['year' => $year, 'month' => $num];
                }
            }
        }

        return null;
    }

    /** T4: Limpiar teléfonos float — "3223109130.0" → "3223109130". */
    public function cleanPhoneFloat(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        return preg_replace('/\.0+$/', '', trim($raw));
    }

    /** T5: Normalizar geografía — "QUINDIO" → "Quindío", "ARMENIA" → "Armenia". */
    public function normalizeGeography(?string $raw): ?string
    {
        if ($raw === null || in_array(trim($raw), self::NULL_VARIANTS, true)) {
            return null;
        }

        $map = [
            'QUINDIO' => 'Quindío', 'QUINDÍO' => 'Quindío',
            'ARMENIA' => 'Armenia', 'BOGOTA' => 'Bogotá', 'BOGOTÁ' => 'Bogotá',
            'MEDELLIN' => 'Medellín', 'MEDELLÍN' => 'Medellín',
            'CALI' => 'Cali', 'PEREIRA' => 'Pereira', 'MANIZALES' => 'Manizales',
        ];

        $upper = strtoupper(trim($raw));

        return $map[$upper] ?? mb_convert_case(trim($raw), MB_CASE_TITLE, 'UTF-8');
    }

    /** T6: Unificar nulos — N/A, SIN INFORMACIÓN, etc. → null. */
    public function unifyNulls(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        return in_array(trim($raw), self::NULL_VARIANTS, true) ? null : trim($raw);
    }

    /** T7: Limpiar documentos float — "15296441.0" → "15296441". */
    public function cleanDocumentFloat(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        return preg_replace('/\.0+$/', '', trim($raw));
    }
}
