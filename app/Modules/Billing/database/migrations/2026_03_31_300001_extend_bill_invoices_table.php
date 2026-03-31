<?php

// Ampliar bill_invoices con campos del Documento Rector §4 Grupo E

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bill_invoices', function (Blueprint $table) {
            $table->string('radicado', 32)->nullable()->after('public_number');
            $table->date('fecha')->nullable()->after('radicado');
            $table->string('service_type_code', 16)->nullable()->after('fecha');
            $table->json('amounts')->nullable()->after('total_pesos');
            $table->string('cancellation_reason', 64)->nullable()->after('estado');
            $table->text('cancellation_motive')->nullable()->after('cancellation_reason');
            $table->string('cancelled_by', 191)->nullable()->after('cancellation_motive');
            $table->foreignId('cuenta_cobro_id')->nullable()->after('payer_id')
                ->constrained('bill_cuentas_cobro')->nullOnDelete();

            $table->index('fecha');
        });
    }

    public function down(): void
    {
        Schema::table('bill_invoices', function (Blueprint $table) {
            $table->dropForeign(['cuenta_cobro_id']);
            $table->dropIndex(['fecha']);
            $table->dropColumn([
                'radicado', 'fecha', 'service_type_code', 'amounts',
                'cancellation_reason', 'cancellation_motive', 'cancelled_by',
                'cuenta_cobro_id',
            ]);
        });
    }
};
