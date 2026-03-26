<?php

namespace Tests\Unit\Modules\RegulatoryEngine;

use App\Modules\RegulatoryEngine\DTOs\CalculationInputDTO;
use App\Modules\RegulatoryEngine\Services\PILACalculationService;
use App\Modules\RegulatoryEngine\ValueObjects\Periodo;
use PHPUnit\Framework\TestCase;

class PILACalculationServiceTest extends TestCase
{
    public function test_calculate_applies_ibc_rounding(): void
    {
        $service = new PILACalculationService;
        $dto = new CalculationInputDTO(
            rawIbcPesos: 1_750_905,
            cotizationPeriod: new Periodo(2026, 3),
            contributorTypeCode: '01',
        );

        $result = $service->calculate($dto);

        $this->assertSame(1_751_000, $result->ibcRoundedPesos);
    }
}
