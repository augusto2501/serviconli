<?php

// RF-110 — gestión de derechos Habeas Data (Ley 1581/2012)

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gdpr_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('affiliate_id')->constrained('afl_affiliates')->cascadeOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 32);
            $table->text('description')->nullable();
            $table->string('status', 32)->default('PENDIENTE');
            $table->text('resolution_notes')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index('affiliate_id', 'gdpr_req_affiliate_idx');
            $table->index('type', 'gdpr_req_type_idx');
            $table->index('status', 'gdpr_req_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gdpr_requests');
    }
};
