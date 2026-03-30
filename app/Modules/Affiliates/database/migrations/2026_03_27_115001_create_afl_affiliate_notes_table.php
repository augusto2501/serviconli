<?php

// DOCUMENTO_RECTOR §4 Grupo B — afl_affiliate_notes; RF-019

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('afl_affiliate_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_id')->constrained('afl_affiliates')->cascadeOnDelete();
            $table->text('note');
            $table->string('note_type', 32);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('afl_affiliate_notes');
    }
};
