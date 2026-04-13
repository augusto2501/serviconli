<?php

namespace App\Modules\PILALiquidation\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use OpenSpout\Reader\XLSX\Reader;

/**
 * RF-118 — ETL desde DataSegura-SERVICONLI-2025.xlsx.
 *
 * 8 transformaciones de limpieza aplicadas a 597 registros:
 *   1. Normalizar NIT (3 formatos → número + DV separado)
 *   2. Parsear MES_PAGO (22 variantes → período YYYYMM + estado)
 *   3. Cifrar credenciales AES-256 (operador + 4 portales)
 *   4. Limpiar teléfonos float ("3223109130.0" → "3223109130")
 *   5. Normalizar geografía ("QUINDIO"/"Quindío" → estándar)
 *   6. Unificar nulos (N/A, SIN INFORMACIÓN → NULL)
 *   7. Limpiar documentos float ("15296441.0" → "15296441")
 *   8. Mapear tipo cliente a enum AffiliateClientType
 *
 * @see DOCUMENTO_RECTOR §16, RF-118, SKILL.md §"Problemas de Calidad del Excel"
 */
final class EtlMigrateExcelCommand extends Command
{
    protected $signature = 'etl:migrate-excel
        {path : Ruta al archivo Excel DataSegura-SERVICONLI-2025.xlsx}
        {--dry-run : Simular sin escribir en BD}';

    protected $description = 'RF-118: Importa y transforma datos desde el Excel maestro de Serviconli';

    private const NULL_VARIANTS = [
        'N/A', 'n/a', 'NA', 'na', 'SIN INFORMACIÓN', 'SIN INFORMACION',
        'SIN INFO', 'NO APLICA', 'NO TIENE', 'NINGUNO', '-', '', ' ',
        'N /A', 'n /a',
    ];

    private const CLIENT_TYPE_MAP = [
        'SERVICONLI' => 'SERVICONLI',
        'INDEPENDIENTE' => 'INDEPENDIENTE',
        'DEPENDIENTE' => 'DEPENDIENTE',
        'COLOMBIANO RECIDENTE EN EL EXTERIOR' => 'COLOMBIANO_EXTERIOR',
        'COLOMBIANO RESIDENTE EN EL EXTERIOR' => 'COLOMBIANO_EXTERIOR',
    ];

    private const MONTH_MAP = [
        'ENERO' => 1, 'FEBRERO' => 2, 'MARZO' => 3, 'ABRIL' => 4,
        'MAYO' => 5, 'JUNIO' => 6, 'JULIO' => 7, 'AGOSTO' => 8,
        'SEPTIEMBRE' => 9, 'OCTUBRE' => 10, 'NOVIEMBRE' => 11, 'DICIEMBRE' => 12,
        'ENE' => 1, 'FEB' => 2, 'MAR' => 3, 'ABR' => 4,
        'MAY' => 5, 'JUN' => 6, 'JUL' => 7, 'AGO' => 8,
        'SEP' => 9, 'OCT' => 10, 'NOV' => 11, 'DIC' => 12,
    ];

    private int $imported = 0;

    private int $skipped = 0;

    private array $warnings = [];

    public function handle(): int
    {
        $path = $this->argument('path');
        $dryRun = $this->option('dry-run');

        if (! file_exists($path)) {
            $this->error("Archivo no encontrado: {$path}");

            return self::FAILURE;
        }

        $this->info($dryRun ? '[DRY-RUN] Simulando importación...' : 'Iniciando ETL Excel...');

        $reader = new Reader;
        $reader->open($path);

        foreach ($reader->getSheetIterator() as $sheet) {
            if ($sheet->getName() !== 'DATA ACTUALIZADA 2025') {
                continue;
            }

            $rowNum = 0;
            try {
                foreach ($sheet->getRowIterator() as $row) {
                    $rowNum++;
                    if ($rowNum === 1) {
                        continue;
                    }

                    $cells = $row->toArray();
                    $this->processRow($cells, $rowNum, $dryRun);
                }
            } catch (\Throwable $e) {
                $this->warn("Error en fila {$rowNum}: {$e->getMessage()}");
            }
        }

        $reader->close();

        $this->newLine();
        $this->info("Importados: {$this->imported} | Omitidos: {$this->skipped}");

        if (count($this->warnings) > 0) {
            $this->warn('Advertencias ('.count($this->warnings).'):');
            foreach (array_slice($this->warnings, 0, 20) as $w) {
                $this->line("  - {$w}");
            }
        }

        return self::SUCCESS;
    }

    private function processRow(array $c, int $rowNum, bool $dryRun): void
    {
        $docNumber = $this->cleanDocFloat($this->val($c, 4));
        if ($docNumber === null || $docNumber === '') {
            $this->skipped++;

            return;
        }

        // T1: Normalizar NIT pagador
        $payerDoc = $this->val($c, 15);
        $payerNit = $this->normalizeNit($payerDoc);

        // T2: Parsear MES_PAGO
        $mesPago = $this->parseMesPago($this->val($c, 47));

        // T3: Credenciales para cifrado AES-256
        $credentials = $this->extractCredentials($c);

        // T4-T7: Limpieza general
        $personData = [
            'document_type' => $this->val($c, 3) ?: 'CC',
            'document_number' => $docNumber,
            'first_name' => $this->extractFirstName($this->val($c, 5)),
            'first_surname' => $this->extractSurname($this->val($c, 5)),
            'gender' => $this->val($c, 6) ?: null,
            'birth_date' => $this->parseDate($this->val($c, 7)),
            'address' => $this->clean($this->val($c, 8)),
            'city_name' => $this->normalizeGeo($this->val($c, 9)),
            'department_name' => $this->normalizeGeo($this->val($c, 10)),
            'cellphone' => $this->cleanPhone($this->val($c, 11)),
            'email' => $this->clean($this->val($c, 12)),
        ];

        // T8: Mapear tipo cliente
        $clientType = self::CLIENT_TYPE_MAP[strtoupper(trim($this->val($c, 1) ?? ''))] ?? 'SERVICONLI';
        $status = strtoupper(trim($this->val($c, 0) ?? ''));

        $affiliateData = [
            'client_type' => $clientType,
            'status_excel' => $status,
            'contributor_type_code' => $this->extractContributorCode($this->val($c, 2)),
            'salary_pesos' => $this->cleanInt($this->val($c, 28)),
            'arl_risk_class' => $this->extractRiskClass($this->val($c, 30)),
            'payment_day' => $this->cleanInt($this->val($c, 21)),
            'periodicity' => $this->val($c, 44),
            'parafiscales' => strtoupper($this->val($c, 42) ?? '') === 'SI',
            'observations_affiliation' => $this->clean($this->val($c, 43)),
            'observations_payment' => $this->clean($this->val($c, 49)),
        ];

        $payerData = [
            'razon_social' => $this->clean($this->val($c, 13)),
            'nit' => $payerNit['nit_body'],
            'digito_verificacion' => $payerNit['digito_verificacion'] !== null ? (int) $payerNit['digito_verificacion'] : null,
        ];

        $entityNames = [
            'arl' => $this->val($c, 29),
            'ccf' => $this->val($c, 33),
            'eps' => $this->val($c, 36),
            'afp' => $this->val($c, 39),
        ];

        $noveltyRaw = $this->val($c, 23);
        $noveltyDate = $this->parseDate($this->val($c, 24));

        if ($dryRun) {
            $this->imported++;
            if ($this->imported % 100 === 0) {
                $this->output->write('.');
            }

            return;
        }

        DB::transaction(function () use (
            $personData, $affiliateData, $payerData, $entityNames,
            $credentials, $mesPago, $noveltyRaw, $noveltyDate, $rowNum
        ) {
            // Person → core_people
            DB::table('core_people')->updateOrInsert(
                ['document_number' => $personData['document_number']],
                array_merge($personData, ['updated_at' => now(), 'created_at' => now()])
            );
            $person = DB::table('core_people')
                ->where('document_number', $personData['document_number'])
                ->first();

            // Payer → afl_payers
            $payerNit = $payerData['nit'];
            if ($payerNit) {
                DB::table('afl_payers')->updateOrInsert(
                    ['nit' => $payerNit],
                    array_merge($payerData, ['status' => 'ACTIVE', 'updated_at' => now(), 'created_at' => now()])
                );
            }
            $payer = $payerNit
                ? DB::table('afl_payers')->where('nit', $payerNit)->first()
                : null;

            // Affiliate → afl_affiliates
            DB::table('afl_affiliates')->updateOrInsert(
                ['person_id' => $person->id],
                [
                    'client_type' => $affiliateData['client_type'],
                    'operational_notes' => $affiliateData['observations_affiliation'],
                    'payment_notes' => $affiliateData['observations_payment'],
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
            $affiliate = DB::table('afl_affiliates')->where('person_id', $person->id)->first();

            // Link affiliate ↔ payer
            if ($payer) {
                DB::table('afl_affiliate_payer')->updateOrInsert(
                    ['affiliate_id' => $affiliate->id, 'payer_id' => $payer->id],
                    [
                        'start_date' => $noveltyDate ?? '2025-01-01',
                        'contributor_type_code' => $affiliateData['contributor_type_code'],
                        'salary' => $affiliateData['salary_pesos'],
                        'status' => 'ACTIVE',
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }

            // Resolve entity IDs from names
            $entityIds = $this->resolveEntityIds($entityNames);

            // Social Security Profile → afl_social_security_profiles
            DB::table('afl_social_security_profiles')->updateOrInsert(
                ['affiliate_id' => $affiliate->id, 'valid_until' => null],
                [
                    'eps_entity_id' => $entityIds['eps'],
                    'afp_entity_id' => $entityIds['afp'],
                    'arl_entity_id' => $entityIds['arl'],
                    'ccf_entity_id' => $entityIds['ccf'],
                    'arl_risk_class' => $affiliateData['arl_risk_class'] ?? 1,
                    'ibc' => $affiliateData['salary_pesos'] ?? 0,
                    'valid_from' => '2025-01-01',
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );

            // T3: Cifrar y guardar credenciales portal → afl_portal_credentials
            foreach ($credentials as $cred) {
                if ($cred['user'] === null && $cred['pass'] === null) {
                    continue;
                }
                DB::table('afl_portal_credentials')->updateOrInsert(
                    ['affiliate_id' => $affiliate->id, 'portal_type' => $cred['portal']],
                    [
                        'username' => $cred['user'],
                        'password' => $cred['pass'] !== null ? Crypt::encryptString((string) $cred['pass']) : null,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }

            // Novelty from Excel (ING, RET, etc.) → afl_novelties
            if ($noveltyRaw !== null && $noveltyRaw !== '') {
                $noveltyCode = $this->extractNoveltyCode($noveltyRaw);
                if ($noveltyCode !== null) {
                    DB::table('afl_novelties')->insert([
                        'affiliate_id' => $affiliate->id,
                        'period_year' => $noveltyDate ? (int) substr($noveltyDate, 0, 4) : 2025,
                        'period_month' => $noveltyDate ? (int) substr($noveltyDate, 5, 2) : 1,
                        'novelty_type_code' => $noveltyCode,
                        'start_date' => $noveltyDate,
                        'notes' => "ETL Excel fila {$rowNum}: {$noveltyRaw}",
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        });

        $this->imported++;
        if ($this->imported % 100 === 0) {
            $this->output->write('.');
        }
    }

    // ── Transformation helpers ──

    public function normalizeNit(?string $raw): array
    {
        if ($raw === null || trim($raw) === '') {
            return ['nit_body' => null, 'digito_verificacion' => null];
        }
        $clean = preg_replace('/[^0-9]/', '', $raw);
        if (strlen($clean) < 2) {
            return ['nit_body' => $clean, 'digito_verificacion' => ''];
        }

        return [
            'nit_body' => substr($clean, 0, -1),
            'digito_verificacion' => substr($clean, -1),
        ];
    }

    public function parseMesPago(?string $raw): ?array
    {
        $raw = $this->clean($raw);
        if ($raw === null) {
            return null;
        }

        $upper = strtoupper($raw);

        if (preg_match('/^(\d{4})-?(\d{2})$/', $upper, $m)) {
            return ['year' => (int) $m[1], 'month' => (int) $m[2]];
        }
        if (preg_match('/^(\d{1,2})[\/\-](\d{4})$/', $upper, $m)) {
            return ['year' => (int) $m[2], 'month' => (int) $m[1]];
        }

        foreach (self::MONTH_MAP as $name => $num) {
            if (str_contains($upper, $name)) {
                if (preg_match('/(\d{2,4})/', $upper, $m)) {
                    $year = (int) $m[1];

                    return ['year' => $year < 100 ? $year + 2000 : $year, 'month' => $num];
                }

                return ['year' => 2025, 'month' => $num];
            }
        }

        return null;
    }

    public function cleanPhoneFloat(?string $raw): ?string
    {
        return $this->cleanPhone($raw);
    }

    public function normalizeGeography(?string $raw): ?string
    {
        return $this->normalizeGeo($raw);
    }

    public function unifyNulls(?string $raw): ?string
    {
        return $this->clean($raw);
    }

    public function cleanDocumentFloat(?string $raw): ?string
    {
        return $this->cleanDocFloat($raw);
    }

    // ── Private helpers ──

    private function val(array $cells, int $i): ?string
    {
        $v = $cells[$i] ?? null;
        if ($v === null) {
            return null;
        }
        if (is_object($v)) {
            return method_exists($v, 'format') ? $v->format('Y-m-d') : (string) $v;
        }

        return (string) $v;
    }

    private function clean(?string $v): ?string
    {
        if ($v === null) {
            return null;
        }
        $trimmed = trim($v);

        return in_array($trimmed, self::NULL_VARIANTS, true) ? null : $trimmed;
    }

    private function cleanPhone(?string $v): ?string
    {
        if ($v === null) {
            return null;
        }

        $v = preg_replace('/\.0+$/', '', trim($v));

        return $this->clean($v);
    }

    private function cleanDocFloat(?string $v): ?string
    {
        if ($v === null) {
            return null;
        }

        return preg_replace('/\.0+$/', '', trim($v));
    }

    private function cleanInt(?string $v): ?int
    {
        $v = $this->clean($v);
        if ($v === null) {
            return null;
        }
        $v = preg_replace('/[^0-9.]/', '', $v);

        return $v !== '' ? (int) round((float) $v) : null;
    }

    private function normalizeGeo(?string $v): ?string
    {
        $v = $this->clean($v);
        if ($v === null) {
            return null;
        }
        $map = [
            'QUINDIO' => 'Quindío', 'QUINDÍO' => 'Quindío',
            'ARMENIA' => 'Armenia', 'BOGOTA' => 'Bogotá', 'BOGOTÁ' => 'Bogotá',
            'MEDELLIN' => 'Medellín', 'MEDELLÍN' => 'Medellín',
            'CALI' => 'Cali', 'PEREIRA' => 'Pereira', 'MANIZALES' => 'Manizales',
            'CALARCA' => 'Calarcá', 'CALARCÁ' => 'Calarcá',
            'FILANDIA' => 'Filandia', 'CIRCASIA' => 'Circasia',
            'MONTENEGRO' => 'Montenegro', 'LA TEBAIDA' => 'La Tebaida',
        ];
        $upper = strtoupper(trim($v));

        return $map[$upper] ?? mb_convert_case(trim($v), MB_CASE_TITLE, 'UTF-8');
    }

    private function parseDate(?string $v): ?string
    {
        $v = $this->clean($v);
        if ($v === null) {
            return null;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $v)) {
            return substr($v, 0, 10);
        }

        return null;
    }

    private function extractFirstName(?string $fullName): string
    {
        if ($fullName === null) {
            return '';
        }
        $parts = preg_split('/\s+/', trim($fullName));

        return $parts[0] ?? '';
    }

    private function extractSurname(?string $fullName): string
    {
        if ($fullName === null) {
            return '';
        }
        $parts = preg_split('/\s+/', trim($fullName));

        return count($parts) > 1 ? $parts[count($parts) - 2] : ($parts[0] ?? '');
    }

    private function extractContributorCode(?string $raw): string
    {
        if ($raw === null) {
            return '03';
        }
        if (preg_match('/^(\d{1,2})/', trim($raw), $m)) {
            return str_pad($m[1], 2, '0', STR_PAD_LEFT);
        }

        return '03';
    }

    private function extractRiskClass(?string $raw): int
    {
        if ($raw === null) {
            return 1;
        }
        if (preg_match('/^(\d)/', trim($raw), $m)) {
            return max(1, min(5, (int) $m[1]));
        }

        return 1;
    }

    private function extractNoveltyCode(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }
        if (preg_match('/^(ING|RET|TAE|TAP|VSP|VST|VTE|VCT|TDE|TDP|LMA|LPA|IGE|IRL|SLN|LLU|AVP|COR)/i', trim($raw), $m)) {
            return strtoupper($m[1]);
        }
        if (stripos($raw, 'RET') !== false) {
            return 'RET';
        }
        if (stripos($raw, 'Ingreso') !== false) {
            return 'ING';
        }

        return null;
    }

    private function extractCredentials(array $c): array
    {
        return [
            ['portal' => 'OPERATOR', 'user' => $this->clean($this->val($c, 26)), 'pass' => $this->clean($this->val($c, 27))],
            ['portal' => 'ARL', 'user' => $this->clean($this->val($c, 31)), 'pass' => $this->clean($this->val($c, 32))],
            ['portal' => 'CCF', 'user' => $this->clean($this->val($c, 34)), 'pass' => $this->clean($this->val($c, 35))],
            ['portal' => 'EPS', 'user' => $this->clean($this->val($c, 37)), 'pass' => $this->clean($this->val($c, 38))],
            ['portal' => 'AFP', 'user' => $this->clean($this->val($c, 40)), 'pass' => $this->clean($this->val($c, 41))],
        ];
    }

    /** Maps variant codes found in Excel to the canonical PILA code in cfg_ss_entities. */
    private const CODE_ALIASES = [
        'EPS-S' => 'EPSIC5',
    ];

    private function resolveEntityIds(array $entityNames): array
    {
        $ids = ['arl' => null, 'ccf' => null, 'eps' => null, 'afp' => null];

        foreach ($entityNames as $type => $raw) {
            if ($raw === null) {
                continue;
            }
            $pilaCode = $this->extractPilaCode($raw);
            if ($pilaCode === null) {
                continue;
            }

            $resolved = self::CODE_ALIASES[$pilaCode] ?? $pilaCode;

            $entity = DB::table('cfg_ss_entities')
                ->where('pila_code', $resolved)
                ->first();

            if ($entity === null) {
                $entity = DB::table('cfg_ss_entities')
                    ->where('name', 'LIKE', '%'.strtoupper($this->extractEntityName($raw)).'%')
                    ->first();
            }

            if ($entity !== null) {
                $ids[$type] = $entity->id;
            } else {
                $this->warnings[] = "Entidad no encontrada: {$raw} (código: {$pilaCode})";
            }
        }

        return $ids;
    }

    private function extractEntityName(?string $raw): string
    {
        if ($raw === null) {
            return '';
        }
        $raw = preg_replace('/\t/', ' ', $raw);
        if (preg_match('/-\s*(.+)$/', $raw, $m)) {
            return trim(preg_replace('/^(EPS|AFP|ARL|CCF)\s*/i', '', trim($m[1])));
        }

        return '';
    }

    private function extractPilaCode(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }
        $raw = trim($raw);
        // Handle tabs: "EPS037\t -EPS LA NUEVA EPS"
        $raw = preg_replace('/\t/', ' ', $raw);
        if (preg_match('/^([A-Z0-9\-]+)/i', $raw, $m)) {
            $code = trim($m[1]);
            if (in_array($code, ['000-0', '00-0', '0', '0000', '00-0-NO'], true)) {
                return null;
            }

            return $code;
        }

        return null;
    }
}
