<?php

// RF-015 / RF-111 — credenciales de portales; hoy texto plano, cifrado vía config + cast

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('afl_portal_credentials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('affiliate_id')->constrained('afl_affiliates')->cascadeOnDelete();
            $table->string('portal_type', 32);
            $table->string('username', 255)->nullable();
            $table->text('password')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['affiliate_id', 'portal_type'], 'afl_portal_cred_aff_type_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('afl_portal_credentials');
    }
};
