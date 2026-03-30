<?php

// DOCUMENTO_RECTOR BC-03 / RF-024–027 — empleadores con NIT validable (módulo 11)

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empl_employers', function (Blueprint $table) {
            $table->id();
            $table->string('nit_body', 16);
            $table->unsignedTinyInteger('digito_verificacion');
            $table->string('razon_social');
            $table->string('status', 32)->default('ACTIVE');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['nit_body', 'digito_verificacion']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empl_employers');
    }
};
