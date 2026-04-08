<?php

// RF-101 — consignaciones bancarias formales

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tp_bank_deposits', function (Blueprint $table): void {
            $table->id();
            $table->string('bank_name', 128);
            $table->string('reference', 64);
            $table->unsignedBigInteger('amount_pesos');
            $table->string('deposit_type', 16);
            $table->unsignedBigInteger('expected_amount_pesos')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('reference', 'tp_bank_dep_ref_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tp_bank_deposits');
    }
};
