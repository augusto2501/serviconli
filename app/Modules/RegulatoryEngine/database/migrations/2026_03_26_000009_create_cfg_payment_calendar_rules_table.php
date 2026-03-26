<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cfg_payment_calendar_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('digit_range_start');
            $table->unsignedTinyInteger('digit_range_end');
            $table->unsignedTinyInteger('business_day');
            $table->timestamps();

            $table->index(['digit_range_start', 'digit_range_end'], 'cfg_pay_cal_range_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cfg_payment_calendar_rules');
    }
};
