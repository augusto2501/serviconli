<?php

namespace Database\Seeders;

use App\Modules\RegulatoryEngine\Enums\SubsystemType;
use App\Modules\RegulatoryEngine\Models\ContributorType;
use App\Modules\RegulatoryEngine\Models\ContributorTypeSubsystem;
use Illuminate\Database\Seeder;

class ContributorTypeSubsystemSeeder extends Seeder
{
    public function run(): void
    {
        $map = [
            // Dependientes: todos los subsistemas obligatorios.
            '01' => [
                [SubsystemType::SALUD->value, true],
                [SubsystemType::PENSION->value, true],
                [SubsystemType::ARL->value, true],
                [SubsystemType::CCF->value, true],
            ],
            '02' => [
                [SubsystemType::SALUD->value, true],
                [SubsystemType::PENSION->value, true],
                [SubsystemType::ARL->value, true],
                [SubsystemType::CCF->value, true],
            ],
            // Independientes base: sin CCF por defecto.
            '03' => [
                [SubsystemType::SALUD->value, true],
                [SubsystemType::PENSION->value, true],
                [SubsystemType::ARL->value, true],
                [SubsystemType::CCF->value, false],
            ],
            // Contratista PS: sin CCF por defecto.
            '57' => [
                [SubsystemType::SALUD->value, true],
                [SubsystemType::PENSION->value, true],
                [SubsystemType::ARL->value, true],
                [SubsystemType::CCF->value, false],
            ],
        ];

        foreach ($map as $contributorTypeCode => $rules) {
            $type = ContributorType::query()->where('code', $contributorTypeCode)->first();
            if ($type === null) {
                continue;
            }

            foreach ($rules as [$subsystem, $isRequired]) {
                ContributorTypeSubsystem::query()->updateOrCreate(
                    [
                        'contributor_type_id' => $type->id,
                        'subsystem' => $subsystem,
                    ],
                    [
                        'is_required' => $isRequired,
                        'distribution_percent' => null,
                    ]
                );
            }
        }
    }
}
