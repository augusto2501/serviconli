<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cfg_pila_occupation_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 7)->unique();
            $table->string('description');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cfg_pila_occupation_codes');
    }
};
