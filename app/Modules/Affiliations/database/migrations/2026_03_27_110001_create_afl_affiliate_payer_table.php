<?php

// DOCUMENTO_RECTOR §4 Grupo B — afl_affiliate_payer; RF-028 / RF-029 (vínculo afiliado–pagador)

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('afl_affiliate_payer', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_id')->constrained('afl_affiliates')->cascadeOnDelete();
            $table->foreignId('payer_id')->constrained('afl_payers')->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('contributor_type_code', 16)->nullable();
            $table->unsignedInteger('salary')->nullable();
            $table->string('position')->nullable();
            $table->string('occupation_code_768', 16)->nullable();
            $table->unsignedBigInteger('advisor_id')->nullable();
            $table->string('enterprise_code', 32)->nullable();
            $table->string('enterprise_name')->nullable();
            $table->string('status', 32)->nullable();
            $table->boolean('affiliation_paid')->default(false);
            $table->timestamps();

            $table->index(['affiliate_id', 'start_date', 'end_date'], 'afl_aff_payer_aff_dates');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('afl_affiliate_payer');
    }
};
