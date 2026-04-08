<?php

// RF-098 — prórrogas de incapacidad

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dis_disability_extensions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('disability_id')->constrained('dis_affiliate_disabilities')->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('disability_id', 'dis_ext_dis_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dis_disability_extensions');
    }
};
