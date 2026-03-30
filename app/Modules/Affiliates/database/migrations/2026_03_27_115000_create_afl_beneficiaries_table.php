<?php

// DOCUMENTO_RECTOR §4 Grupo B — afl_beneficiaries; RF-017

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('afl_beneficiaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_id')->constrained('afl_affiliates')->cascadeOnDelete();
            $table->string('document_type', 16)->nullable();
            $table->string('document_number', 32);
            $table->string('first_name')->nullable();
            $table->string('surnames')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('gender', 16)->nullable();
            $table->string('parentesco', 64)->nullable();
            $table->foreignId('eps_entity_id')->nullable()->constrained('cfg_ss_entities')->nullOnDelete();
            $table->date('student_cert_expires')->nullable();
            $table->string('disability_type', 64)->nullable();
            $table->date('protection_end_date')->nullable();
            $table->string('status', 32)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('afl_beneficiaries');
    }
};
