<?php

// RF-012 / RF-014 — códigos de estado mínimos para reingreso (RETIRADO, INACTIVO, AFILIADO)

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $rows = [
            ['code' => 'AFILIADO', 'name' => 'Afiliado', 'sort_order' => 10],
            ['code' => 'INACTIVO', 'name' => 'Inactivo', 'sort_order' => 70],
            ['code' => 'RETIRADO', 'name' => 'Retirado', 'sort_order' => 80],
        ];

        foreach ($rows as $row) {
            DB::table('cfg_affiliate_statuses')->insertOrIgnore([
                'code' => $row['code'],
                'name' => $row['name'],
                'sort_order' => $row['sort_order'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('cfg_affiliate_statuses')->whereIn('code', ['AFILIADO', 'INACTIVO', 'RETIRADO'])->delete();
    }
};
