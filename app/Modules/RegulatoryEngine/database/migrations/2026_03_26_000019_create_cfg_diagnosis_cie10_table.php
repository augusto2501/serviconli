<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cfg_diagnosis_cie10', function (Blueprint $table) {
            $table->id();
            $table->string('code', 16)->unique();
            $table->string('description');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cfg_diagnosis_cie10');
    }
};
