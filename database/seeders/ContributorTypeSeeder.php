<?php

namespace Database\Seeders;

use App\Modules\RegulatoryEngine\Models\ContributorType;
use Illuminate\Database\Seeder;

class ContributorTypeSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['code' => '01', 'name' => 'Dependiente general'],
            ['code' => '02', 'name' => 'Dependiente servicio domestico'],
            ['code' => '03', 'name' => 'Independiente general'],
            ['code' => '57', 'name' => 'Contratista prestacion de servicios'],
        ];

        foreach ($rows as $row) {
            ContributorType::query()->updateOrCreate(
                ['code' => $row['code']],
                [
                    'name' => $row['name'],
                    'subsystems' => null,
                    'ibc_rules' => null,
                    'legal_basis' => 'Seeder base inicial',
                    'is_active' => true,
                ]
            );
        }
    }
}
