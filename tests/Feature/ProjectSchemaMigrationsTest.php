<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProjectSchemaMigrationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_pay_liquidation_and_operational_tables_exist(): void
    {
        foreach ([
            'pay_liquidation_batches',
            'pay_liquidation_batch_lines',
            'pay_liquidation_entity_summary',
            'afl_beneficiaries',
            'afl_affiliate_notes',
            'afl_payers',
            'afl_affiliate_payer',
            'afl_social_security_profiles',
            'empl_employers',
            'bill_invoices',
            'cash_daily_closures',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Falta tabla {$table}");
        }
    }
}
