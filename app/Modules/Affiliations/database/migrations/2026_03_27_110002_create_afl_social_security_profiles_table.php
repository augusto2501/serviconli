<?php

// DOCUMENTO_RECTOR §4 Grupo B — afl_social_security_profiles; RF-028 / RF-029 (historial EPS/AFP con vigencias)

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('afl_social_security_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_id')->constrained('afl_affiliates')->cascadeOnDelete();
            $table->foreignId('eps_entity_id')->nullable()->constrained('cfg_ss_entities')->nullOnDelete();
            $table->foreignId('afp_entity_id')->nullable()->constrained('cfg_ss_entities')->nullOnDelete();
            $table->foreignId('arl_entity_id')->nullable()->constrained('cfg_ss_entities')->nullOnDelete();
            $table->foreignId('ccf_entity_id')->nullable()->constrained('cfg_ss_entities')->nullOnDelete();
            $table->decimal('eps_tarifa', 8, 4)->nullable();
            $table->decimal('afp_tarifa', 8, 4)->nullable();
            $table->decimal('arl_tarifa', 8, 4)->nullable();
            $table->unsignedTinyInteger('arl_risk_class')->nullable();
            $table->decimal('ccf_tarifa', 8, 4)->nullable();
            $table->unsignedInteger('ibc')->nullable();
            $table->unsignedInteger('admin_fee')->nullable();
            $table->date('valid_from');
            $table->date('valid_until')->nullable();
            $table->timestamps();

            $table->index(['affiliate_id', 'valid_from', 'valid_until'], 'afl_ss_prof_aff_valid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('afl_social_security_profiles');
    }
};
