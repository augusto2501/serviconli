<?php

// RF-111 — log específico de acceso/descifrado de credenciales cifradas

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sec_credential_access_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('credential_id')->constrained('afl_portal_credentials')->cascadeOnDelete();
            $table->string('action', 32)->default('DECRYPT');
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['credential_id', 'created_at'], 'sec_cred_access_cred_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sec_credential_access_logs');
    }
};
