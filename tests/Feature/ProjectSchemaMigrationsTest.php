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
            'afl_multi_income_contracts',
            'empl_employers',
            'bill_invoices',
            'cash_daily_closures',
            'cash_daily_reconciliations',
            'cash_recon_affiliations',
            'cash_recon_contributions',
            'cash_recon_cuentas',
            'cash_daily_close',
            'radicado_yearly_sequences',
            'gdpr_consent_records',
            'wf_reentry_processes',
            'afl_portal_credentials',
            'sec_advisors',
            'bill_advisor_commissions',
            'tp_bank_deposits',
            'tp_advisor_receivables',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Falta tabla {$table}");
        }
    }
}
