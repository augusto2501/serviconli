<?php

// DOCUMENTO_RECTOR §4 Grupo E — bill_invoices (núcleo BC-06; ampliar conceptos en iteraciones)

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bill_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('public_number', 32)->nullable()->unique();
            $table->foreignId('affiliate_id')->nullable()->constrained('afl_affiliates')->nullOnDelete();
            $table->foreignId('payer_id')->nullable()->constrained('afl_payers')->nullOnDelete();
            $table->string('tipo', 32)->nullable();
            $table->string('payment_method', 32)->nullable();
            $table->unsignedBigInteger('total_pesos')->default(0);
            $table->string('estado', 32)->default('ACTIVO');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bill_invoices');
    }
};
