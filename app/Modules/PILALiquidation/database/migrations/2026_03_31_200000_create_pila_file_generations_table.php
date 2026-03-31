<?php

// DOCUMENTO_RECTOR §4 Grupo M — pila_file_generations

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pila_file_generations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payer_id')->nullable()->constrained('afl_payers')->nullOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('pay_liquidation_batches')->nullOnDelete();
            $table->unsignedSmallInteger('period_year');
            $table->unsignedTinyInteger('period_month');
            $table->string('planilla_type', 8)->default('E');
            $table->unsignedBigInteger('operator_id')->nullable();
            $table->string('branch_code', 32)->nullable();
            $table->string('planilla_number', 64)->nullable();
            $table->date('payment_date')->nullable();
            $table->unsignedInteger('affiliates_count')->default(0);
            $table->string('file_path', 500)->nullable();
            $table->string('file_format', 16)->default('PLANO_ARUS');
            $table->string('generated_by', 191)->nullable();
            $table->string('status', 32)->default('GENERADO');
            $table->timestamps();

            $table->index(['payer_id', 'period_year', 'period_month'], 'pila_fg_payer_period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pila_file_generations');
    }
};
