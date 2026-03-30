<?php

namespace Tests\Unit\Modules\RegulatoryEngine;

use App\Modules\RegulatoryEngine\Models\RegulatoryParameter;
use App\Modules\RegulatoryEngine\Models\SolidarityFundScale;
use App\Modules\RegulatoryEngine\Repositories\RegulatoryParameterRepository;
use App\Modules\RegulatoryEngine\Services\SolidarityFundCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SolidarityFundCalculatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_zero_without_smmlv_parameter(): void
    {
        $calc = new SolidarityFundCalculator(new RegulatoryParameterRepository);
        $r = $calc->compute(10_000_000, '2026-03-01');
        $this->assertSame(0, $r['pesos']);
        $this->assertNull($r['rate_percent']);
    }

    public function test_applies_highest_tramo_for_ibc_in_smmlv(): void
    {
        RegulatoryParameter::query()->create([
            'category' => 'monetary',
            'key' => 'SMMLV',
            'value' => '1000000',
            'data_type' => 'integer',
            'legal_basis' => 'Test',
            'valid_from' => '2026-01-01',
            'valid_until' => null,
        ]);

        foreach ([
            ['min_smmlv' => 4, 'rate' => '1.0'],
            ['min_smmlv' => 16, 'rate' => '1.2'],
            ['min_smmlv' => 20, 'rate' => '2.0'],
        ] as $row) {
            SolidarityFundScale::query()->create([
                'min_smmlv' => $row['min_smmlv'],
                'rate' => $row['rate'],
                'valid_from' => '2026-01-01',
                'valid_until' => null,
            ]);
        }

        $calc = new SolidarityFundCalculator(new RegulatoryParameterRepository);

        // 10 SMMLV → tramo ≥4, tasa 1%
        $r10 = $calc->compute(10_000_000, '2026-06-01');
        $this->assertSame(100_000, $r10['pesos']);
        $this->assertSame(1.0, $r10['rate_percent']);
        $this->assertSame(4.0, $r10['min_smmlv_bracket']);

        // 25 SMMLV → tramo ≥20, tasa 2%
        $r25 = $calc->compute(25_000_000, '2026-06-01');
        $this->assertSame(500_000, $r25['pesos']);
        $this->assertSame(2.0, $r25['rate_percent']);
        $this->assertSame(20.0, $r25['min_smmlv_bracket']);
    }
}
