<?php

namespace Database\Seeders;

use App\Modules\RegulatoryEngine\Models\ColombianHoliday;
use Illuminate\Database\Seeder;

/**
 * Festivos Colombia 2026 (Ley Emiliani / calendario oficial; fechas de puente según decreto).
 * Revisar ante cambios normativos.
 */
class ColombianHoliday2026Seeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['2026-01-01', 'Año Nuevo', 'Ley 51/1983 (Emiliani)'],
            ['2026-01-12', 'Día de los Reyes Magos', 'Traslado Ley Emiliani'],
            ['2026-03-23', 'Día de San José', 'Ley Emiliani'],
            ['2026-04-02', 'Jueves Santo', 'Semana Santa'],
            ['2026-04-03', 'Viernes Santo', 'Semana Santa'],
            ['2026-05-01', 'Día del Trabajo', ''],
            ['2026-05-18', 'Ascensión del Señor', 'Ley Emiliani'],
            ['2026-06-08', 'Corpus Christi', 'Ley Emiliani'],
            ['2026-06-15', 'Sagrado Corazón de Jesús', 'Ley Emiliani'],
            ['2026-06-29', 'San Pedro y San Pablo', 'Ley Emiliani'],
            ['2026-07-20', 'Día de la Independencia', ''],
            ['2026-08-07', 'Batalla de Boyacá', ''],
            ['2026-08-17', 'Asunción de la Virgen', 'Ley Emiliani'],
            ['2026-10-12', 'Día de la Raza', 'Ley Emiliani'],
            ['2026-11-02', 'Día de Todos los Santos', 'Ley Emiliani'],
            ['2026-11-16', 'Independencia de Cartagena', 'Ley Emiliani'],
            ['2026-12-08', 'Inmaculada Concepción', ''],
            ['2026-12-25', 'Navidad', ''],
        ];

        foreach ($rows as [$date, $name, $law]) {
            ColombianHoliday::query()->updateOrCreate(
                ['holiday_date' => $date],
                ['name' => $name, 'law_basis' => $law ?: null]
            );
        }
    }
}
