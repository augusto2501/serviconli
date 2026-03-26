<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cfg_disability_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 16)->unique();
            $table->string('name');
            $table->string('category', 8);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cfg_disability_types');
    }
};
