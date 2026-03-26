<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cfg_novelty_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('novelty_type_id')
                ->constrained('cfg_novelty_types')
                ->cascadeOnDelete();
            $table->string('subsystem', 32);
            $table->string('effect_type', 64);
            $table->text('formula')->nullable();
            $table->timestamps();

            $table->index(['novelty_type_id', 'subsystem'], 'cfg_nov_rule_type_sub_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cfg_novelty_rules');
    }
};
