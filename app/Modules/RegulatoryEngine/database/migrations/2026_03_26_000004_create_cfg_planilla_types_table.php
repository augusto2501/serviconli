<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cfg_planilla_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 4)->unique();
            $table->string('name');
            $table->json('allowed_contributors')->nullable();
            $table->json('allowed_novelties')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cfg_planilla_types');
    }
};
