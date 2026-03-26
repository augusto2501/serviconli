<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cfg_pila_file_format_fields', function (Blueprint $table) {
            $table->id();
            $table->string('record_type', 8)->nullable();
            $table->string('field_name', 64);
            $table->unsignedSmallInteger('position_start');
            $table->unsignedSmallInteger('length');
            $table->string('pad_char', 1)->default(' ');
            $table->string('description')->nullable();
            $table->timestamps();

            $table->index(['record_type', 'position_start'], 'cfg_pila_fmt_pos_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cfg_pila_file_format_fields');
    }
};
