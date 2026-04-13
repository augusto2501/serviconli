<?php

namespace Tests\Feature\RegulatoryEngine;

use App\Modules\RegulatoryEngine\Services\LegacyComparisonService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BreachasAndCatalogTest extends TestCase
{
    use RefreshDatabase;

    // ── RF-044: legacy vs PILA comparison ──

    public function test_legacy_comparison_within_tolerance_returns_null(): void
    {
        $service = new LegacyComparisonService;
        $result = $service->compare(150000, 149500);

        $this->assertNull($result);
    }

    public function test_legacy_comparison_exceeds_tolerance_returns_alert(): void
    {
        $service = new LegacyComparisonService;
        $result = $service->compare(160000, 150000);

        $this->assertNotNull($result);
        $this->assertStringContainsString('RF-044', $result['message']);
        $this->assertGreaterThan(1.0, $result['difference_percent']);
    }

    public function test_legacy_comparison_null_reference_returns_null(): void
    {
        $service = new LegacyComparisonService;
        $result = $service->compare(150000, null);

        $this->assertNull($result);
    }

    // ── RF-116: admin catalogs API ──

    public function test_catalogs_list_returns_all_allowed_tables(): void
    {
        $r = $this->getJson('/api/admin/catalogs');
        $r->assertOk();
        $r->assertJsonCount(27);
    }

    public function test_catalog_index_returns_paginated_data(): void
    {
        $r = $this->getJson('/api/admin/catalogs/cfg_contributor_types');
        $r->assertOk();
        $r->assertJsonStructure(['data', 'current_page', 'total']);
    }

    public function test_catalog_rejects_non_whitelisted_table(): void
    {
        $r = $this->getJson('/api/admin/catalogs/users');
        $r->assertStatus(400);
    }

    public function test_catalog_crud_for_regulatory_parameters(): void
    {
        $r = $this->postJson('/api/admin/catalogs/cfg_regulatory_parameters', [
            'category' => 'rates',
            'key' => 'TEST_PARAM',
            'value' => '0.125',
            'valid_from' => '2026-01-01',
        ]);
        $r->assertCreated();
        $id = $r->json('id');

        $r2 = $this->getJson("/api/admin/catalogs/cfg_regulatory_parameters/{$id}");
        $r2->assertOk();
        $r2->assertJsonPath('key', 'TEST_PARAM');

        $r3 = $this->putJson("/api/admin/catalogs/cfg_regulatory_parameters/{$id}", [
            'value' => '0.130',
        ]);
        $r3->assertOk();
        $r3->assertJsonPath('value', '0.130');

        $r4 = $this->deleteJson("/api/admin/catalogs/cfg_regulatory_parameters/{$id}");
        $r4->assertOk();
    }

    // ── ETL commands exist ──

    public function test_etl_excel_command_fails_for_missing_file(): void
    {
        $this->artisan('etl:migrate-excel', ['path' => '/tmp/no_existe_xyz.xlsx'])
            ->assertExitCode(1);
    }

    public function test_etl_access_command_fails_for_missing_dir(): void
    {
        $this->artisan('etl:migrate-access', ['path' => '/tmp/no_existe_xyz_dir/'])
            ->assertExitCode(1);
    }

    // ── ETL transformations ──

    public function test_etl_normalize_nit(): void
    {
        $cmd = new \App\Modules\PILALiquidation\Commands\EtlMigrateExcelCommand;
        $r = $cmd->normalizeNit('900966567-4');
        $this->assertSame('900966567', $r['nit_body']);
        $this->assertSame('4', $r['digito_verificacion']);
    }

    public function test_etl_parse_mes_pago_variants(): void
    {
        $cmd = new \App\Modules\PILALiquidation\Commands\EtlMigrateExcelCommand;

        $this->assertSame(['year' => 2025, 'month' => 1], $cmd->parseMesPago('ENERO 2025'));
        $this->assertSame(['year' => 2025, 'month' => 1], $cmd->parseMesPago('2025-01'));
        $this->assertSame(['year' => 2025, 'month' => 3], $cmd->parseMesPago('03/2025'));
        $this->assertSame(['year' => 2025, 'month' => 6], $cmd->parseMesPago('JUN-25'));
        $this->assertNull($cmd->parseMesPago('N/A'));
    }

    public function test_etl_clean_phone_float(): void
    {
        $cmd = new \App\Modules\PILALiquidation\Commands\EtlMigrateExcelCommand;
        $this->assertSame('3223109130', $cmd->cleanPhoneFloat('3223109130.0'));
        $this->assertSame('3001234567', $cmd->cleanPhoneFloat('3001234567'));
    }

    public function test_etl_normalize_geography(): void
    {
        $cmd = new \App\Modules\PILALiquidation\Commands\EtlMigrateExcelCommand;
        $this->assertSame('Quindío', $cmd->normalizeGeography('QUINDIO'));
        $this->assertSame('Armenia', $cmd->normalizeGeography('ARMENIA'));
        $this->assertSame('Bogotá', $cmd->normalizeGeography('BOGOTA'));
    }

    public function test_etl_unify_nulls(): void
    {
        $cmd = new \App\Modules\PILALiquidation\Commands\EtlMigrateExcelCommand;
        $this->assertNull($cmd->unifyNulls('N/A'));
        $this->assertNull($cmd->unifyNulls('SIN INFORMACIÓN'));
        $this->assertSame('valid', $cmd->unifyNulls('valid'));
    }
}
