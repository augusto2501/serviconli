<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cfg_solidarity_fund_scale', function (Blueprint $table) {
            $table->id();
            $table->decimal('min_smmlv', 8, 2);
            $table->decimal('rate', 8, 4);
            $table->date('valid_from');
            $table->date('valid_until')->nullable();
            $table->timestamps();

            $table->index(['valid_from', 'valid_until'], 'cfg_solid_scale_valid_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cfg_solidarity_fund_scale');
    }
};
