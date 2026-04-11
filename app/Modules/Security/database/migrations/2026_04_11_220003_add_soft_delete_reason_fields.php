<?php

// RF-112 — soft delete con motivo obligatorio en modelos críticos

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = [
            'afl_affiliates',
            'core_people',
            'empl_employers',
            'afl_novelties',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'deleted_reason')) {
                Schema::table($table, function (Blueprint $t): void {
                    $t->text('deleted_reason')->nullable()->after('deleted_at');
                    $t->unsignedBigInteger('deleted_by')->nullable()->after('deleted_reason');
                });
            }
        }

        $tablesNeedingSoftDelete = [
            'bill_invoices',
            'pila_liquidations',
            'afl_social_security_profiles',
            'afl_affiliate_payer',
            'afl_beneficiaries',
        ];

        foreach ($tablesNeedingSoftDelete as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'deleted_at')) {
                Schema::table($table, function (Blueprint $t): void {
                    $t->softDeletes();
                    $t->text('deleted_reason')->nullable()->after('deleted_at');
                    $t->unsignedBigInteger('deleted_by')->nullable()->after('deleted_reason');
                });
            }
        }
    }

    public function down(): void
    {
        $allTables = [
            'afl_affiliates', 'core_people', 'empl_employers', 'afl_novelties',
            'bill_invoices', 'pila_liquidations', 'afl_social_security_profiles',
            'afl_affiliate_payer', 'afl_beneficiaries',
        ];

        foreach ($allTables as $table) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $t) use ($table): void {
                    $cols = ['deleted_reason', 'deleted_by'];
                    foreach ($cols as $col) {
                        if (Schema::hasColumn($table, $col)) {
                            $t->dropColumn($col);
                        }
                    }
                });
            }
        }

        $tablesNeedingSoftDelete = [
            'bill_invoices', 'pila_liquidations', 'afl_social_security_profiles',
            'afl_affiliate_payer', 'afl_beneficiaries',
        ];

        foreach ($tablesNeedingSoftDelete as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'deleted_at')) {
                Schema::table($table, function (Blueprint $t): void {
                    $t->dropSoftDeletes();
                });
            }
        }
    }
};
