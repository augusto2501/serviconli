<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

/**
 * RF-118 — Ejecuta el ETL del Excel real como parte del seeding.
 *
 * Si el archivo docs/DataSegura-SERVICONLI-2025.xlsx existe, importa
 * los 596 registros reales. Si no existe, lo omite silenciosamente.
 *
 * @see DOCUMENTO_RECTOR §16
 */
class ExcelEtlSeeder extends Seeder
{
    public function run(): void
    {
        $path = base_path('docs/DataSegura-SERVICONLI-2025.xlsx');

        if (! file_exists($path)) {
            $this->command?->warn('ExcelEtlSeeder: archivo no encontrado, omitiendo ETL.');

            return;
        }

        $this->command?->info('ExcelEtlSeeder: importando datos reales desde Excel...');

        Artisan::call('etl:migrate-excel', ['path' => $path]);

        $this->command?->info(Artisan::output());
    }
}
