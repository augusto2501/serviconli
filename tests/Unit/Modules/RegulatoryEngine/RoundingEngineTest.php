<?php

namespace Tests\Unit\Modules\RegulatoryEngine;

use App\Modules\RegulatoryEngine\Enums\RoundingMethod;
use App\Modules\RegulatoryEngine\Services\RoundingEngine;
use PHPUnit\Framework\TestCase;

class RoundingEngineTest extends TestCase
{
    public function test_ibc_method_matches_millar_superior(): void
    {
        $engine = new RoundingEngine;

        $this->assertSame(1_751_000, $engine->round(1_750_905, RoundingMethod::IBC));
        $this->assertSame(0, $engine->round(0, RoundingMethod::IBC));
    }
}
