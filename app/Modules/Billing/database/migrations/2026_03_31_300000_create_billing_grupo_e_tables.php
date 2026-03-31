<?php

// DOCUMENTO_RECTOR §4 Grupo E — Cartera Serviconli (tablas faltantes)

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Contratos de servicio (plan, tarifa, genera cuenta cobro)
        Schema::create('bill_service_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payer_id')->constrained('afl_payers')->cascadeOnDelete();
            $table->string('plan', 64);
            $table->unsignedBigInteger('tarifa_admin_pesos')->default(0);
            $table->unsignedBigInteger('tarifa_affiliation_pesos')->default(0);
            $table->date('vigencia_start');
            $table->date('vigencia_end')->nullable();
            $table->boolean('generates_cuenta_cobro')->default(true);
            $table->string('status', 32)->default('ACTIVO');
            $table->timestamps();

            $table->index(['payer_id', 'status']);
        });

        // Cuentas de cobro — RN-16
        Schema::create('bill_cuentas_cobro', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payer_id')->constrained('afl_payers')->cascadeOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('pay_liquidation_batches')->nullOnDelete();
            $table->string('cuenta_number', 32)->unique();
            $table->unsignedSmallInteger('period_year');
            $table->unsignedTinyInteger('period_month');
            $table->string('period_cobro', 16)->nullable();
            $table->string('period_servicio', 16)->nullable();
            $table->string('generation_mode', 32)->default('PLENO');
            $table->unsignedBigInteger('total_eps')->default(0);
            $table->unsignedBigInteger('total_afp')->default(0);
            $table->unsignedBigInteger('total_arl')->default(0);
            $table->unsignedBigInteger('total_ccf')->default(0);
            $table->unsignedBigInteger('total_admin')->default(0);
            $table->unsignedBigInteger('total_affiliation')->default(0);
            $table->date('payment_date_1')->nullable();
            $table->unsignedBigInteger('total_1')->default(0);
            $table->date('payment_date_2')->nullable();
            $table->unsignedBigInteger('interest_mora')->default(0);
            $table->unsignedBigInteger('total_2')->default(0);
            $table->string('status', 32)->default('PRE_CUENTA');
            $table->date('payment_date')->nullable();
            $table->unsignedBigInteger('payment_amount')->nullable();
            $table->string('cancellation_reason', 64)->nullable();
            $table->text('cancellation_motive')->nullable();
            $table->string('cancelled_by', 191)->nullable();
            $table->timestamps();

            $table->index(['payer_id', 'period_year', 'period_month'], 'bill_cc_payer_period');
        });

        // Detalle por afiliado de cada cuenta de cobro
        Schema::create('bill_cuenta_cobro_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cuenta_cobro_id')->constrained('bill_cuentas_cobro')->cascadeOnDelete();
            $table->foreignId('affiliate_id')->constrained('afl_affiliates')->cascadeOnDelete();
            $table->unsignedBigInteger('health_pesos')->default(0);
            $table->unsignedBigInteger('pension_pesos')->default(0);
            $table->unsignedBigInteger('arl_pesos')->default(0);
            $table->unsignedBigInteger('ccf_pesos')->default(0);
            $table->unsignedBigInteger('admin_pesos')->default(0);
            $table->unsignedBigInteger('affiliation_pesos')->default(0);
            $table->unsignedBigInteger('total_pesos')->default(0);
            $table->timestamps();

            $table->index(['cuenta_cobro_id', 'affiliate_id']);
        });

        // Detalle conceptos del recibo (bill_invoices ya existe)
        Schema::create('bill_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('bill_invoices')->cascadeOnDelete();
            $table->unsignedTinyInteger('line_number')->default(1);
            $table->string('concept', 100);
            $table->unsignedBigInteger('amount_pesos')->default(0);
            $table->timestamps();
        });

        // Pagos recibidos
        Schema::create('bill_payments_received', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->nullable()->constrained('bill_invoices')->nullOnDelete();
            $table->foreignId('cuenta_cobro_id')->nullable()->constrained('bill_cuentas_cobro')->nullOnDelete();
            $table->foreignId('affiliate_id')->nullable()->constrained('afl_affiliates')->nullOnDelete();
            $table->foreignId('payer_id')->nullable()->constrained('afl_payers')->nullOnDelete();
            $table->string('payment_method', 32);
            $table->unsignedBigInteger('amount_pesos');
            $table->date('payment_date');
            $table->string('bank_name', 100)->nullable();
            $table->string('bank_reference', 50)->nullable();
            $table->string('status', 32)->default('APLICADO');
            $table->string('received_by', 191)->nullable();
            $table->timestamps();

            $table->index('payment_date');
        });

        // Cartera por cliente/asesor
        Schema::create('bill_accounts_receivable', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_id')->nullable()->constrained('afl_affiliates')->nullOnDelete();
            $table->foreignId('payer_id')->nullable()->constrained('afl_payers')->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('bill_invoices')->nullOnDelete();
            $table->string('concept', 100);
            $table->unsignedBigInteger('amount_pesos');
            $table->unsignedBigInteger('balance_pesos');
            $table->date('due_date')->nullable();
            $table->string('status', 32)->default('PENDIENTE');
            $table->date('paid_date')->nullable();
            $table->timestamps();

            $table->index(['status', 'due_date']);
        });

        // Cotizaciones — RN-19
        Schema::create('bill_quotations', function (Blueprint $table) {
            $table->id();
            $table->string('prospect_name', 200);
            $table->string('prospect_document', 20)->nullable();
            $table->string('prospect_phone', 20)->nullable();
            $table->string('prospect_email', 100)->nullable();
            $table->unsignedBigInteger('salary_pesos');
            $table->string('contributor_type_code', 3)->default('01');
            $table->unsignedTinyInteger('arl_risk_class')->default(1);
            $table->json('amounts')->nullable();
            $table->string('pdf_path', 500)->nullable();
            $table->string('created_by', 191)->nullable();
            $table->timestamps();
        });

        // Templates de contratos/fichas — RN-23
        Schema::create('bill_contract_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->longText('content_html');
            $table->json('variables')->nullable();
            $table->unsignedSmallInteger('version')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bill_contract_templates');
        Schema::dropIfExists('bill_quotations');
        Schema::dropIfExists('bill_accounts_receivable');
        Schema::dropIfExists('bill_payments_received');
        Schema::dropIfExists('bill_invoice_items');
        Schema::dropIfExists('bill_cuenta_cobro_details');
        Schema::dropIfExists('bill_cuentas_cobro');
        Schema::dropIfExists('bill_service_contracts');
    }
};
