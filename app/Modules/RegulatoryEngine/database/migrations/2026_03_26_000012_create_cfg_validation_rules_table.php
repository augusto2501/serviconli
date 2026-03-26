<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cfg_validation_rules', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->text('rule_expression');
            $table->string('error_message');
            $table->string('severity', 16)->default('error');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cfg_validation_rules');
    }
};
