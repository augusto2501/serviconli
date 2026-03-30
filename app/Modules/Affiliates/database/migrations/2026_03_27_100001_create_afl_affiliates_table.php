<?php

// DOCUMENTO_RECTOR §4 Grupo B — afl_affiliates; RF-002–RF-007 (clasificación y estado)

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('afl_affiliates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained('core_people')->cascadeOnDelete();
            $table->string('client_type', 32);
            $table->foreignId('status_id')->nullable()->constrained('cfg_affiliate_statuses')->nullOnDelete();
            $table->string('mora_status', 64)->nullable();
            $table->string('ips_code', 32)->nullable();
            $table->boolean('has_discount')->default(false);
            $table->text('discount_notes')->nullable();
            $table->boolean('is_type_51')->default(false);
            $table->unsignedSmallInteger('subtipo')->nullable();
            $table->text('operational_notes')->nullable();
            $table->text('payment_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('afl_affiliates');
    }
};
