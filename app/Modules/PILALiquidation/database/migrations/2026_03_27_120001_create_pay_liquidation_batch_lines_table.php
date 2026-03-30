<?php

// DOCUMENTO_RECTOR §4 Grupo D — pay_liquidation_batch_lines

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pay_liquidation_batch_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('pay_liquidation_batches')->cascadeOnDelete();
            $table->foreignId('affiliate_id')->constrained('afl_affiliates')->cascadeOnDelete();
            $table->foreignId('ss_profile_id')->nullable()->constrained('afl_social_security_profiles')->nullOnDelete();
            $table->unsignedInteger('ibc')->default(0);
            $table->unsignedInteger('ibc2')->nullable();
            $table->unsignedInteger('salary')->nullable();
            $table->unsignedTinyInteger('days_eps')->nullable();
            $table->unsignedTinyInteger('days_afp')->nullable();
            $table->unsignedTinyInteger('days_arl')->nullable();
            $table->unsignedTinyInteger('days_ccf')->nullable();
            $table->unsignedBigInteger('health_employer')->default(0);
            $table->unsignedBigInteger('health_employee')->default(0);
            $table->unsignedBigInteger('health_total')->default(0);
            $table->unsignedBigInteger('pension_employer')->default(0);
            $table->unsignedBigInteger('pension_employee')->default(0);
            $table->unsignedBigInteger('pension_total')->default(0);
            $table->unsignedBigInteger('arl_total')->default(0);
            $table->unsignedBigInteger('ccf_total')->default(0);
            $table->unsignedBigInteger('solidarity')->default(0);
            $table->unsignedBigInteger('upc')->default(0);
            $table->unsignedBigInteger('admin_fee')->default(0);
            $table->unsignedBigInteger('affiliation_fee')->default(0);
            $table->unsignedBigInteger('interest_mora')->default(0);
            $table->unsignedBigInteger('total_ss')->default(0);
            $table->unsignedBigInteger('total_payable')->default(0);
            $table->string('contributor_type_code', 16)->nullable();
            $table->string('occupation_code_768', 16)->nullable();
            $table->unsignedSmallInteger('subtipo')->nullable();
            $table->json('novelties')->nullable();
            $table->string('retirement_scope', 16)->nullable();
            $table->string('service_code', 32)->nullable();
            $table->string('payment_method', 32)->nullable();
            $table->boolean('has_exception')->default(false);
            $table->foreignId('exception_id')->nullable()->constrained('cfg_operational_exceptions')->nullOnDelete();
            $table->string('line_status', 32)->default('INCLUIDO');
            $table->timestamps();

            $table->index(['batch_id', 'affiliate_id'], 'pay_liq_line_batch_aff');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pay_liquidation_batch_lines');
    }
};
