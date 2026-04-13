<?php

// RF-075/087 — vincular consignaciones con recibos y afiliados

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tp_bank_deposits', function (Blueprint $table): void {
            if (! Schema::hasColumn('tp_bank_deposits', 'invoice_id')) {
                $table->foreignId('invoice_id')->nullable()->after('id')->constrained('bill_invoices')->nullOnDelete();
            }
            if (! Schema::hasColumn('tp_bank_deposits', 'affiliate_id')) {
                $table->foreignId('affiliate_id')->nullable()->after('invoice_id')->constrained('afl_affiliates')->nullOnDelete();
            }
            if (! Schema::hasColumn('tp_bank_deposits', 'concept')) {
                $table->string('concept', 128)->nullable()->after('notes');
            }
            if (! Schema::hasColumn('tp_bank_deposits', 'status')) {
                $table->string('status', 32)->default('ACTIVO')->after('concept');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tp_bank_deposits', function (Blueprint $table): void {
            $cols = ['invoice_id', 'affiliate_id', 'concept', 'status'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('tp_bank_deposits', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
