<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cfg_ss_entities', function (Blueprint $table) {
            $table->id();
            $table->string('pila_code', 16)->unique();
            $table->string('name');
            $table->string('type', 32);
            $table->string('status', 32)->default('ACTIVE');
            $table->string('operator_format', 32)->nullable();
            $table->timestamps();

            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cfg_ss_entities');
    }
};
