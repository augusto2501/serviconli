<?php

namespace Tests\Unit\Modules\RegulatoryEngine;

use App\Modules\RegulatoryEngine\Enums\RoundingMethod;
use App\Modules\RegulatoryEngine\Services\RoundingEngine;
use PHPUnit\Framework\TestCase;

class RoundingEngineTest extends TestCase
{
    public function test_rn_01_ibc_method_matches_millar_superior(): void
    {
        $engine = new RoundingEngine;

        $this->assertSame(1_751_000, $engine->round(1_750_905, RoundingMethod::IBC));
        $this->assertSame(1_424_000, $engine->round(1_423_500, RoundingMethod::IBC));
        $this->assertSame(2_000_000, $engine->round(2_000_000, RoundingMethod::IBC));
        $this->assertSame(1_001_000, $engine->round(1_000_001, RoundingMethod::IBC));
        $this->assertSame(0, $engine->round(0, RoundingMethod::IBC));
        $this->assertSame(0, $engine->round(-100, RoundingMethod::IBC));
    }

    public function test_legacy_method_rounds_to_centenar_superior(): void
    {
        $engine = new RoundingEngine;

        $this->assertSame(218_900, $engine->round(218_875, RoundingMethod::LEGACY));
        $this->assertSame(280_200, $engine->round(280_160, RoundingMethod::LEGACY));
        $this->assertSame(9_200, $engine->round(9_140, RoundingMethod::LEGACY));
        $this->assertSame(70_100, $engine->round(70_040, RoundingMethod::LEGACY));
        $this->assertSame(100_000, $engine->round(100_000, RoundingMethod::LEGACY));
        $this->assertSame(100_100, $engine->round(100_001, RoundingMethod::LEGACY));
        $this->assertSame(0, $engine->round(0, RoundingMethod::LEGACY));
    }

    public function test_pila_method_uses_half_up_rounding(): void
    {
        $engine = new RoundingEngine;

        $this->assertSame(100, $engine->round(100, RoundingMethod::PILA));
        $this->assertSame(0, $engine->round(0, RoundingMethod::PILA));
    }

    public function test_static_methods_match_instance_methods(): void
    {
        $this->assertSame(1_751_000, RoundingEngine::roundIBC(1_750_905));
        $this->assertSame(218_900, RoundingEngine::roundLegacy(218_875));
        $this->assertSame(100, RoundingEngine::roundPILA(100));
    }
}
