<?php

// RF-102 — CxC a asesores (medio CRÉDITO)

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tp_advisor_receivables', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('advisor_id')->constrained('sec_advisors')->restrictOnDelete();
            $table->foreignId('bill_invoice_id')->unique()->constrained('bill_invoices')->restrictOnDelete();
            $table->unsignedBigInteger('amount_pesos');
            $table->string('status', 16)->default('PENDIENTE');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['advisor_id', 'status'], 'tp_adv_rec_adv_st_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tp_advisor_receivables');
    }
};
