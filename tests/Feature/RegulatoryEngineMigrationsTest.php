<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RegulatoryEngineMigrationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (! in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('Se requiere PDO SQLite para migraciones en entorno de pruebas.');
        }

        parent::setUp();
    }

    public function test_cfg_tables_exist(): void
    {
        foreach ([
            'cfg_regulatory_parameters',
            'cfg_contributor_types',
            'cfg_contributor_type_subsystems',
            'cfg_ss_entities',
            'cfg_operational_exceptions',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Falta tabla {$table}");
        }
    }
}
