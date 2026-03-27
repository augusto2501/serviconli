<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pila_liquidations', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 26)->unique();
            $table->string('status', 32);
            $table->string('contributor_type_code', 10);
            $table->unsignedTinyInteger('arl_risk_class');
            $table->date('payment_date');
            $table->unsignedTinyInteger('document_last_two_digits');
            $table->string('target_type', 100)->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('subject_type', 100)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->unsignedBigInteger('total_social_security_pesos');
            $table->json('subsystem_totals_pesos');
            $table->timestamps();

            $table->index('status');
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pila_liquidations');
    }
};
