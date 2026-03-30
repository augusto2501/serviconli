<?php

// RF-008, RF-009 — radicado anual con secuencia bloqueada; registro consentimiento Habeas Data

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('radicado_yearly_sequences', function (Blueprint $table): void {
            $table->id();
            $table->unsignedSmallInteger('year')->unique();
            $table->unsignedInteger('last_sequence')->default(0);
            $table->timestamps();
        });

        Schema::create('gdpr_consent_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('enrollment_process_id')->constrained('wf_enrollment_processes')->cascadeOnDelete();
            $table->foreignId('affiliate_id')->nullable()->constrained('afl_affiliates')->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('accepted_at');
            $table->timestamps();

            $table->index(['affiliate_id', 'accepted_at'], 'gdpr_consent_affiliate_accepted_idx');
        });

        Schema::table('wf_enrollment_processes', function (Blueprint $table): void {
            $table->string('radicado_number', 32)->nullable()->after('affiliate_id');
        });
    }

    public function down(): void
    {
        Schema::table('wf_enrollment_processes', function (Blueprint $table): void {
            $table->dropColumn('radicado_number');
        });
        Schema::dropIfExists('gdpr_consent_records');
        Schema::dropIfExists('radicado_yearly_sequences');
    }
};
