<?php

namespace Database\Seeders;

use App\Modules\RegulatoryEngine\Models\SolidarityFundScale;
use Illuminate\Database\Seeder;

/** Ley 797/2003 Art.25 — tramos mínimos en SMMLV y tasa. */
class SolidarityFundScaleSeeder extends Seeder
{
    public function run(): void
    {
        $from = '2026-01-01';

        $tramos = [
            ['min_smmlv' => 4, 'rate' => '1.0'],
            ['min_smmlv' => 16, 'rate' => '1.2'],
            ['min_smmlv' => 17, 'rate' => '1.4'],
            ['min_smmlv' => 18, 'rate' => '1.6'],
            ['min_smmlv' => 19, 'rate' => '1.8'],
            ['min_smmlv' => 20, 'rate' => '2.0'],
        ];

        foreach ($tramos as $row) {
            SolidarityFundScale::query()->updateOrCreate(
                [
                    'min_smmlv' => $row['min_smmlv'],
                    'valid_from' => $from,
                ],
                [
                    'rate' => $row['rate'],
                    'valid_until' => null,
                ]
            );
        }
    }
}
