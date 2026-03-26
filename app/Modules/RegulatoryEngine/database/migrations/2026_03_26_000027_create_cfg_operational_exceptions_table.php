<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cfg_operational_exceptions', function (Blueprint $table) {
            $table->id();
            $table->string('exception_type', 48);
            $table->string('target_type', 32);
            $table->unsignedBigInteger('target_id');
            $table->json('value')->nullable();
            $table->text('reason')->nullable();
            $table->string('authorized_by')->nullable();
            $table->date('valid_from');
            $table->date('valid_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['target_type', 'target_id', 'is_active'], 'cfg_op_ex_target_active');
            $table->index(['exception_type', 'is_active'], 'cfg_op_ex_type_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cfg_operational_exceptions');
    }
};
