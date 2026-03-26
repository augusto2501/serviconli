<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cfg_ciiu_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 8)->unique();
            $table->string('description');
            $table->unsignedTinyInteger('arl_risk_class')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cfg_ciiu_codes');
    }
};
