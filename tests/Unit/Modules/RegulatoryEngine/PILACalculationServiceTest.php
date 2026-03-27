<?php

namespace Tests\Unit\Modules\RegulatoryEngine;

use App\Modules\RegulatoryEngine\DTOs\CalculationInputDTO;
use App\Modules\RegulatoryEngine\Exceptions\MissingRegulatoryParameterException;
use App\Modules\RegulatoryEngine\Models\RegulatoryParameter;
use App\Modules\RegulatoryEngine\Repositories\RegulatoryParameterRepository;
use App\Modules\RegulatoryEngine\Services\PILACalculationService;
use App\Modules\RegulatoryEngine\ValueObjects\Periodo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PILACalculationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_calculate_applies_ibc_rounding(): void
    {
        $this->seedDefaultRates();

        $service = new PILACalculationService(
            operationalExceptions: null,
            regulatoryParameters: new RegulatoryParameterRepository,
        );
        $dto = new CalculationInputDTO(
            rawIbcPesos: 1_750_905,
            cotizationPeriod: new Periodo(2026, 3),
            contributorTypeCode: '01',
        );

        $result = $service->calculate($dto);

        $this->assertSame(1_751_000, $result->ibcRoundedPesos);
        $this->assertSame(218_875, $result->subsystemAmountsPesos['health_total_pesos']);
        $this->assertSame(280_160, $result->subsystemAmountsPesos['pension_total_pesos']);
        $this->assertSame(9_140, $result->subsystemAmountsPesos['arl_total_pesos']);
        $this->assertSame(70_040, $result->subsystemAmountsPesos['ccf_total_pesos']);
        $this->assertSame(0, $result->subsystemAmountsPesos['mora_interest_pesos']);
        $this->assertSame(0.0833, $result->subsystemAmountsPesos['mora_rate_percent']);
        $this->assertSame(578_215, $result->totalSocialSecurityPesos);
    }

    public function test_calculate_computes_default_mora_interest_when_days_late_are_provided(): void
    {
        $this->seedDefaultRates();

        $service = new PILACalculationService(
            operationalExceptions: null,
            regulatoryParameters: new RegulatoryParameterRepository,
        );
        $dto = new CalculationInputDTO(
            rawIbcPesos: 1_750_905,
            cotizationPeriod: new Periodo(2026, 3),
            contributorTypeCode: '01',
        );

        $result = $service->calculate($dto, null, null, null, 10);

        $this->assertSame(14_586, $result->subsystemAmountsPesos['mora_interest_pesos']);
        $this->assertSame(592_801, $result->totalSocialSecurityPesos);
    }

    public function test_calculate_throws_when_required_rate_is_missing(): void
    {
        $this->seedDefaultRates(skip: ['rates.ARL_RISK_CLASS_I_PERCENT']);

        $service = new PILACalculationService(
            operationalExceptions: null,
            regulatoryParameters: new RegulatoryParameterRepository,
        );
        $dto = new CalculationInputDTO(
            rawIbcPesos: 1_750_905,
            cotizationPeriod: new Periodo(2026, 3),
            contributorTypeCode: '01',
        );

        $this->expectException(MissingRegulatoryParameterException::class);
        $service->calculate($dto);
    }

    public function test_calculate_uses_arl_rate_for_requested_risk_class(): void
    {
        $this->seedDefaultRates();

        $service = new PILACalculationService(
            operationalExceptions: null,
            regulatoryParameters: new RegulatoryParameterRepository,
        );
        $dto = new CalculationInputDTO(
            rawIbcPesos: 1_750_905,
            cotizationPeriod: new Periodo(2026, 3),
            contributorTypeCode: '01',
            arlRiskClass: 5,
        );

        $result = $service->calculate($dto);

        $this->assertSame(121_870, $result->subsystemAmountsPesos['arl_total_pesos']);
        $this->assertSame(690_945, $result->totalSocialSecurityPesos);
    }

    private function seedDefaultRates(array $skip = []): void
    {
        $rows = [
            ['rates', 'SALUD_TOTAL_PERCENT', '12.5'],
            ['rates', 'PENSION_TOTAL_PERCENT', '16'],
            ['rates', 'ARL_RISK_CLASS_I_PERCENT', '0.522'],
            ['rates', 'ARL_RISK_CLASS_II_PERCENT', '1.044'],
            ['rates', 'ARL_RISK_CLASS_III_PERCENT', '2.436'],
            ['rates', 'ARL_RISK_CLASS_IV_PERCENT', '4.350'],
            ['rates', 'ARL_RISK_CLASS_V_PERCENT', '6.960'],
            ['rates', 'CCF_DEPENDIENTE_PERCENT', '4'],
            ['rates', 'CCF_INDEPENDIENTE_PERCENT', '2'],
            ['mora', 'DAILY_RATE_PERCENT', '0.0833'],
        ];

        foreach ($rows as [$category, $key, $value]) {
            if (in_array("{$category}.{$key}", $skip, true)) {
                continue;
            }

            RegulatoryParameter::query()->create([
                'category' => $category,
                'key' => $key,
                'value' => $value,
                'data_type' => 'decimal',
                'legal_basis' => 'Test',
                'valid_from' => '2026-01-01',
                'valid_until' => null,
            ]);
        }
    }
}
