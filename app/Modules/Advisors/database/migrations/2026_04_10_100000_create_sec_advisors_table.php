<?php

// RF-099 — asesores comerciales

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sec_advisors', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('document_type', 8)->nullable();
            $table->string('document_number', 32)->nullable();
            $table->string('first_name', 128);
            $table->string('last_name', 128)->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('email', 255)->nullable();
            $table->unsignedBigInteger('commission_new')->default(0);
            $table->unsignedBigInteger('commission_recurring')->default(0);
            $table->boolean('authorizes_credits')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sec_advisors');
    }
};
