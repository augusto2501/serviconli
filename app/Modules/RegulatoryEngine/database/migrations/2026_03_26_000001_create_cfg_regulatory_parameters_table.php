<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cfg_regulatory_parameters', function (Blueprint $table) {
            $table->id();
            $table->string('category', 64);
            $table->string('key', 128);
            $table->text('value');
            $table->string('data_type', 32)->nullable();
            $table->text('legal_basis')->nullable();
            $table->date('valid_from');
            $table->date('valid_until')->nullable();
            $table->timestamps();

            $table->unique(['category', 'key', 'valid_from'], 'cfg_reg_param_cat_key_from_unique');
            $table->index(['category', 'key'], 'cfg_reg_param_cat_key_idx');
            $table->index(['valid_from', 'valid_until'], 'cfg_reg_param_valid_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cfg_regulatory_parameters');
    }
};
