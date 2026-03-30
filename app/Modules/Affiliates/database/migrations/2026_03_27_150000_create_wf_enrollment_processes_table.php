<?php

// RF-001 — wizard de 6 pasos (backend first)

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wf_enrollment_processes', function (Blueprint $table): void {
            $table->id();
            $table->string('status', 16)->default('DRAFT');
            $table->unsignedTinyInteger('current_step')->default(0);
            $table->json('step1_payload')->nullable();
            $table->json('step2_payload')->nullable();
            $table->json('step3_payload')->nullable();
            $table->json('step4_payload')->nullable();
            $table->json('step5_payload')->nullable();
            $table->foreignId('affiliate_id')->nullable()->constrained('afl_affiliates')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'current_step'], 'wf_enrollment_status_step_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wf_enrollment_processes');
    }
};
