<?php

// RF-100 — comprobante CE-{YYYY}-{NNNN}

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bill_advisor_commissions', function (Blueprint $table): void {
            $table->id();
            $table->string('public_number', 32)->unique();
            $table->foreignId('advisor_id')->constrained('sec_advisors')->restrictOnDelete();
            $table->foreignId('affiliate_id')->constrained('afl_affiliates')->restrictOnDelete();
            $table->foreignId('enrollment_process_id')->nullable()->constrained('wf_enrollment_processes')->nullOnDelete();
            $table->foreignId('reentry_process_id')->nullable()->constrained('wf_reentry_processes')->nullOnDelete();
            $table->string('commission_type', 16);
            $table->unsignedBigInteger('amount_pesos')->default(0);
            $table->string('status', 16)->default('CALCULADA');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['advisor_id', 'status'], 'bill_adv_comm_adv_st_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bill_advisor_commissions');
    }
};
