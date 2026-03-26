<?php

namespace Tests\Unit\Modules\RegulatoryEngine;

use App\Modules\RegulatoryEngine\ValueObjects\IBC;
use PHPUnit\Framework\TestCase;

class IbcTest extends TestCase
{
    public function test_round_to_millar_superior(): void
    {
        // SMMLV 2026 oficial $1.750.905 → millar superior $1.751.000
        $this->assertSame(1_751_000, IBC::fromRaw(1_750_905)->roundToMillarSuperior()->valueInPesos);
        $this->assertSame(2_000_000, IBC::fromRaw(1_999_001)->roundToMillarSuperior()->valueInPesos);
        $this->assertSame(1_000_000, IBC::fromRaw(1_000_000)->roundToMillarSuperior()->valueInPesos);
    }

    public function test_non_positive_becomes_zero(): void
    {
        $this->assertSame(0, IBC::fromRaw(0)->roundToMillarSuperior()->valueInPesos);
        $this->assertSame(0, IBC::fromRaw(-100)->roundToMillarSuperior()->valueInPesos);
    }
}
