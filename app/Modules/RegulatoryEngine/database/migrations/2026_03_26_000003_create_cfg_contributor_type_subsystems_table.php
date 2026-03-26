<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cfg_contributor_type_subsystems', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contributor_type_id')
                ->constrained('cfg_contributor_types')
                ->cascadeOnDelete();
            $table->string('subsystem', 32);
            $table->boolean('is_required')->default(true);
            $table->decimal('distribution_percent', 8, 4)->nullable();
            $table->timestamps();

            $table->unique(['contributor_type_id', 'subsystem'], 'cfg_ct_subsystems_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cfg_contributor_type_subsystems');
    }
};
