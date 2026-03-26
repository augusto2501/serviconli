<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cfg_novelty_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 16)->unique();
            $table->string('name');
            $table->unsignedSmallInteger('effect_days')->nullable();
            $table->string('effect_ibc', 64)->nullable();
            $table->string('who_pays', 64)->nullable();
            $table->text('legal_basis')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cfg_novelty_types');
    }
};
