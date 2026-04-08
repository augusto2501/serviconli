<?php

// RF-106 — log envíos WhatsApp

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comm_whatsapp_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('affiliate_id')->nullable()->constrained('afl_affiliates')->nullOnDelete();
            $table->string('template_code', 64);
            $table->string('to_number', 32)->nullable();
            $table->string('provider', 16)->default('log');
            $table->string('external_id', 64)->nullable();
            $table->string('status', 16);
            $table->json('payload')->nullable();
            $table->text('error_message')->nullable();
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['affiliate_id', 'template_code'], 'comm_wa_aff_tpl_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comm_whatsapp_logs');
    }
};
