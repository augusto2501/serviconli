<?php

// RF-107 — notificaciones internas por usuario

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comm_notifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 64);
            $table->string('title', 255);
            $table->text('body');
            $table->timestamp('read_at')->nullable();
            $table->string('action_url', 512)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at'], 'comm_notif_user_read_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comm_notifications');
    }
};
