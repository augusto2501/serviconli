<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cfg_disability_subtypes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('disability_type_id')
                ->constrained('cfg_disability_types')
                ->cascadeOnDelete();
            $table->string('code', 16);
            $table->string('name');
            $table->json('required_documents')->nullable();
            $table->timestamps();

            $table->unique(['disability_type_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cfg_disability_subtypes');
    }
};
