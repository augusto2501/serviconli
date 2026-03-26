<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cfg_pila_operator_branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operator_id')
                ->constrained('cfg_ss_entities')
                ->cascadeOnDelete();
            $table->string('branch_code', 16);
            $table->string('branch_name');
            $table->timestamps();

            $table->unique(['operator_id', 'branch_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cfg_pila_operator_branches');
    }
};
