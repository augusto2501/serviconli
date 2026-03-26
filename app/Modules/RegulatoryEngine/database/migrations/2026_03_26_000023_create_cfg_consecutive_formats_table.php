<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cfg_consecutive_formats', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('pattern');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cfg_consecutive_formats');
    }
};
