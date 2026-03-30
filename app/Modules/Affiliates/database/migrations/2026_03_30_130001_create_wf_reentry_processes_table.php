<?php

// RF-012–RF-014 — proceso de reingreso (borrador por pasos + confirmación)

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wf_reentry_processes', function (Blueprint $table): void {
            $table->id();
            $table->string('status', 16)->default('DRAFT');
            $table->unsignedTinyInteger('current_step')->default(0);
            $table->foreignId('affiliate_id')->constrained('afl_affiliates')->cascadeOnDelete();
            $table->json('step1_payload')->nullable();
            $table->json('step2_payload')->nullable();
            $table->json('step3_payload')->nullable();
            $table->foreignId('bill_invoice_id')->nullable()->constrained('bill_invoices')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'affiliate_id'], 'wf_reentry_status_aff_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wf_reentry_processes');
    }
};
