<?php

// RF-097 — incapacidades EPS/ARL con CIE-10

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dis_affiliate_disabilities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('affiliate_id')->constrained('afl_affiliates')->cascadeOnDelete();
            $table->string('source', 32);
            $table->string('subtype_code', 64);
            $table->foreignId('diagnosis_cie10_id')->constrained('cfg_diagnosis_cie10')->restrictOnDelete();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->json('submitted_documents')->nullable();
            $table->unsignedInteger('cumulative_days')->default(0);
            $table->boolean('over_180_alert')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['affiliate_id', 'source'], 'dis_aff_src_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dis_affiliate_disabilities');
    }
};
