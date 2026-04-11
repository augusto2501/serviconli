<?php

// RF-109 — tabla de auditoría completa: usuario, acción, modelo, valores antes/después, IP

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sec_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 32);
            $table->string('auditable_type', 128);
            $table->unsignedBigInteger('auditable_id');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamps();

            $table->index(['auditable_type', 'auditable_id'], 'sec_audit_auditable_idx');
            $table->index('user_id', 'sec_audit_user_idx');
            $table->index('action', 'sec_audit_action_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sec_audit_logs');
    }
};
