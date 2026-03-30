<?php

// RF-024 — campos extendidos de empleadores/pagadores.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empl_employers', function (Blueprint $table): void {
            $table->string('nombre_corto')->nullable()->after('razon_social');
            $table->string('representante_legal')->nullable()->after('nombre_corto');
            $table->string('representante_documento', 32)->nullable()->after('representante_legal');
            $table->string('tipo_persona', 32)->nullable()->after('representante_documento');
            $table->string('naturaleza_juridica', 64)->nullable()->after('tipo_persona');
            $table->string('actividad_economica_code', 32)->nullable()->after('naturaleza_juridica');
            $table->string('address')->nullable()->after('actividad_economica_code');
            $table->string('city_name')->nullable()->after('address');
            $table->string('department_name')->nullable()->after('city_name');
            $table->string('phone', 32)->nullable()->after('department_name');
            $table->string('email')->nullable()->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('empl_employers', function (Blueprint $table): void {
            $table->dropColumn([
                'nombre_corto',
                'representante_legal',
                'representante_documento',
                'tipo_persona',
                'naturaleza_juridica',
                'actividad_economica_code',
                'address',
                'city_name',
                'department_name',
                'phone',
                'email',
            ]);
        });
    }
};
