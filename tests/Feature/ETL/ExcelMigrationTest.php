<?php

namespace Tests\Feature\ETL;

use App\Modules\PILALiquidation\Commands\EtlMigrateExcelCommand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * RF-118 — Tests de transformaciones ETL del Excel.
 */
class ExcelMigrationTest extends TestCase
{
    use RefreshDatabase;

    private EtlMigrateExcelCommand $cmd;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cmd = new EtlMigrateExcelCommand;
    }

    // ── T1: NIT ──

    public function test_normalize_nit_with_dash(): void
    {
        $r = $this->cmd->normalizeNit('901776975-4');
        $this->assertSame('901776975', $r['nit_body']);
        $this->assertSame('4', $r['digito_verificacion']);
    }

    public function test_normalize_nit_without_dash(): void
    {
        $r = $this->cmd->normalizeNit('9009665674');
        $this->assertSame('900966567', $r['nit_body']);
        $this->assertSame('4', $r['digito_verificacion']);
    }

    public function test_normalize_nit_with_dots(): void
    {
        $r = $this->cmd->normalizeNit('900.966.567-4');
        $this->assertSame('900966567', $r['nit_body']);
        $this->assertSame('4', $r['digito_verificacion']);
    }

    public function test_normalize_nit_null(): void
    {
        $r = $this->cmd->normalizeNit(null);
        $this->assertNull($r['nit_body']);
    }

    // ── T2: MES_PAGO ──

    public function test_parse_mes_pago_nombre_completo(): void
    {
        $r = $this->cmd->parseMesPago('SEPTIEMBRE');
        $this->assertSame(['year' => 2025, 'month' => 9], $r);
    }

    public function test_parse_mes_pago_nombre_con_ano(): void
    {
        $r = $this->cmd->parseMesPago('ENERO 2025');
        $this->assertSame(['year' => 2025, 'month' => 1], $r);
    }

    public function test_parse_mes_pago_iso(): void
    {
        $r = $this->cmd->parseMesPago('2025-03');
        $this->assertSame(['year' => 2025, 'month' => 3], $r);
    }

    public function test_parse_mes_pago_slash(): void
    {
        $r = $this->cmd->parseMesPago('06/2025');
        $this->assertSame(['year' => 2025, 'month' => 6], $r);
    }

    public function test_parse_mes_pago_abreviado(): void
    {
        $r = $this->cmd->parseMesPago('JUN-25');
        $this->assertSame(['year' => 2025, 'month' => 6], $r);
    }

    public function test_parse_mes_pago_null_variant(): void
    {
        $this->assertNull($this->cmd->parseMesPago('N/A'));
        $this->assertNull($this->cmd->parseMesPago(null));
        $this->assertNull($this->cmd->parseMesPago('SIN INFORMACIÓN'));
    }

    // ── T4: Teléfonos ──

    public function test_clean_phone_float(): void
    {
        $this->assertSame('3223109130', $this->cmd->cleanPhoneFloat('3223109130.0'));
        $this->assertSame('3001234567', $this->cmd->cleanPhoneFloat('3001234567'));
    }

    // ── T5: Geografía ──

    public function test_normalize_geography(): void
    {
        $this->assertSame('Quindío', $this->cmd->normalizeGeography('QUINDIO'));
        $this->assertSame('Armenia', $this->cmd->normalizeGeography('ARMENIA'));
        $this->assertSame('Calarcá', $this->cmd->normalizeGeography('CALARCA'));
        $this->assertSame('Filandia', $this->cmd->normalizeGeography('FILANDIA'));
    }

    // ── T6: Nulos ──

    public function test_unify_nulls(): void
    {
        $this->assertNull($this->cmd->unifyNulls('N/A'));
        $this->assertNull($this->cmd->unifyNulls('SIN INFORMACIÓN'));
        $this->assertNull($this->cmd->unifyNulls('NO APLICA'));
        $this->assertSame('dato real', $this->cmd->unifyNulls('dato real'));
    }

    // ── T7: Documentos float ──

    public function test_clean_document_float(): void
    {
        $this->assertSame('15296441', $this->cmd->cleanDocumentFloat('15296441.0'));
        $this->assertSame('24574636', $this->cmd->cleanDocumentFloat('24574636'));
    }

    // ── Dry-run con archivo real ──

    public function test_dry_run_processes_all_records(): void
    {
        $path = base_path('docs/DataSegura-SERVICONLI-2025.xlsx');
        if (! file_exists($path)) {
            $this->markTestSkipped('Excel file not available');
        }

        $this->artisan('etl:migrate-excel', ['path' => $path, '--dry-run' => true])
            ->assertExitCode(0)
            ->expectsOutputToContain('Importados:');
    }

    // ── Seeder ──

    public function test_excel_catalog_seeder_runs(): void
    {
        $this->seed(\Database\Seeders\ExcelCatalogSeeder::class);

        $this->assertDatabaseHas('cfg_contributor_types', ['code' => '01', 'name' => 'Dependiente']);
        $this->assertDatabaseHas('cfg_contributor_types', ['code' => '57']);
        $this->assertDatabaseHas('cfg_ss_entities', ['pila_code' => '25-14', 'name' => 'COLPENSIONES']);
        $this->assertDatabaseHas('cfg_ss_entities', ['pila_code' => 'CCF43', 'name' => 'COMFENALCO QUINDIO']);
        $this->assertDatabaseHas('cfg_regulatory_parameters', ['key' => 'ARL_RISK_1_RATE', 'value' => '0.00522']);
        $this->assertDatabaseHas('cfg_regulatory_parameters', ['key' => 'ARL_RISK_5_RATE', 'value' => '0.06960']);
        $this->assertDatabaseHas('cfg_payment_calendar_rules', ['business_day' => 12, 'digit_range_start' => 70, 'digit_range_end' => 75]);
    }
}
