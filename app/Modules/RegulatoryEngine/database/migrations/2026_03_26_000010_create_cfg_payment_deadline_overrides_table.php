<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cfg_payment_deadline_overrides', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('period_year');
            $table->unsignedTinyInteger('period_month');
            $table->date('deadline_date');
            $table->date('mora_date')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->unique(['period_year', 'period_month'], 'cfg_pay_deadline_period_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cfg_payment_deadline_overrides');
    }
};
