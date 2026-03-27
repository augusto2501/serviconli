<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pila_liquidations', function (Blueprint $table) {
            $table->dropIndex(['subject_type', 'subject_id']);
            $table->dropColumn(['subject_type', 'subject_id']);
        });

        Schema::table('pila_liquidations', function (Blueprint $table) {
            $table->foreignId('affiliate_id')->nullable()->after('target_id')->constrained('affiliates')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pila_liquidations', function (Blueprint $table) {
            $table->dropForeign(['affiliate_id']);
            $table->dropColumn('affiliate_id');
        });

        Schema::table('pila_liquidations', function (Blueprint $table) {
            $table->string('subject_type', 100)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->index(['subject_type', 'subject_id']);
        });
    }
};
