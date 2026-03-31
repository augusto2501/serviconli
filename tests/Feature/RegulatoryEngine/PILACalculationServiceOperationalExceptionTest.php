<?php

namespace Tests\Feature\RegulatoryEngine;

use App\Modules\RegulatoryEngine\DTOs\CalculationInputDTO;
use App\Modules\RegulatoryEngine\Enums\ExceptionType;
use App\Modules\RegulatoryEngine\Models\OperationalException;
use App\Modules\RegulatoryEngine\Models\RegulatoryParameter;
use App\Modules\RegulatoryEngine\Repositories\RegulatoryParameterRepository;
use App\Modules\RegulatoryEngine\Services\PILACalculationService;
use App\Modules\RegulatoryEngine\ValueObjects\Periodo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PILACalculationServiceOperationalExceptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_calculation_applies_mora_rate_override_when_target_is_provided(): void
    {
        $this->seedDefaultRates();

        OperationalException::query()->create([
            'exception_type' => ExceptionType::MORA_EXEMPT->value,
            'target_type' => 'AFFILIATE',
            'target_id' => 999,
            'value' => null,
            'valid_from' => '2026-01-01',
            'valid_until' => null,
            'is_active' => true,
        ]);

        OperationalException::query()->create([
            'exception_type' => ExceptionType::MORA_RATE_OVERRIDE->value,
            'target_type' => 'AFFILIATE',
            'target_id' => 999,
            'value' => ['rate_percent' => 0.031],
            'valid_from' => '2026-01-01',
            'valid_until' => null,
            'is_active' => true,
        ]);

        $service = new PILACalculationService(
            operationalExceptions: null,
            regulatoryParameters: new RegulatoryParameterRepository,
        );
        $dto = new CalculationInputDTO(
            rawIbcPesos: 1_750_905,
            cotizationPeriod: new Periodo(2026, 3),
            contributorTypeCode: '01',
        );

        $result = $service->calculate($dto, 'AFFILIATE', 999, '2026-03-01', 10);

        $this->assertSame(1_751_000, $result->ibcRoundedPesos);
        $this->assertTrue($result->subsystemAmountsPesos['mora_exempt']);
        $this->assertSame(0, $result->subsystemAmountsPesos['mora_interest_pesos']);
        // roundLegacy: total = 218900+280200+9200+70100 = 578400
        $this->assertSame(578_400, $result->totalSocialSecurityPesos);
    }

    public function test_calculation_uses_mora_rate_override_when_not_exempt(): void
    {
        $this->seedDefaultRates();

        OperationalException::query()->create([
            'exception_type' => ExceptionType::MORA_RATE_OVERRIDE->value,
            'target_type' => 'AFFILIATE',
            'target_id' => 111,
            'value' => ['rate_percent' => 0.05],
            'valid_from' => '2026-01-01',
            'valid_until' => null,
            'is_active' => true,
        ]);

        $service = new PILACalculationService(
            operationalExceptions: null,
            regulatoryParameters: new RegulatoryParameterRepository,
        );
        $dto = new CalculationInputDTO(
            rawIbcPesos: 1_750_905,
            cotizationPeriod: new Periodo(2026, 3),
            contributorTypeCode: '01',
        );

        $result = $service->calculate($dto, 'AFFILIATE', 111, '2026-03-01', 10);

        $this->assertFalse($result->subsystemAmountsPesos['mora_exempt']);
        // Override daily=0.05 → monthly=1.5%. Mora sobre TotalAportePOS(578400)
        // Round((((578400/30)×0.015)×10)/100,0)×100 = 2900
        $this->assertSame(2_900, $result->subsystemAmountsPesos['mora_interest_pesos']);
        // Total = 578400 + 2900 = 581300
        $this->assertSame(581_300, $result->totalSocialSecurityPesos);
    }

    public function test_calculation_uses_rates_from_cfg_regulatory_parameters_when_repository_is_provided(): void
    {
        $params = [
            ['rates', 'SALUD_TOTAL_PERCENT', '13'],
            ['rates', 'PENSION_TOTAL_PERCENT', '17'],
            ['rates', 'ARL_RISK_CLASS_I_PERCENT', '1.0'],
            ['rates', 'CCF_DEPENDIENTE_PERCENT', '5'],
            ['mora', 'DAILY_RATE_PERCENT', '0.1'],
        ];

        foreach ($params as [$category, $key, $value]) {
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

        $service = new PILACalculationService(
            operationalExceptions: null,
            regulatoryParameters: new RegulatoryParameterRepository,
        );
        $dto = new CalculationInputDTO(
            rawIbcPesos: 1_750_905,
            cotizationPeriod: new Periodo(2026, 3),
            contributorTypeCode: '01',
        );

        $result = $service->calculate($dto, null, null, '2026-03-01', 10);

        // roundLegacy: 227700, 297700, 17600, 87600
        $this->assertSame(227_700, $result->subsystemAmountsPesos['health_total_pesos']);
        $this->assertSame(297_700, $result->subsystemAmountsPesos['pension_total_pesos']);
        $this->assertSame(17_600, $result->subsystemAmountsPesos['arl_total_pesos']);
        $this->assertSame(87_600, $result->subsystemAmountsPesos['ccf_total_pesos']);
        // TotalPOS=630600. Mora: monthly=0.1*30=3.0. Round((((630600/30)×0.03)×10)/100,0)×100=6300
        $this->assertSame(6_300, $result->subsystemAmountsPesos['mora_interest_pesos']);
        // Total = 630600 + 6300 = 636900
        $this->assertSame(636_900, $result->totalSocialSecurityPesos);
    }

    public function test_calculation_uses_strategy_for_contributor_type_57(): void
    {
        $this->seedDefaultRates();

        $service = new PILACalculationService(
            operationalExceptions: null,
            regulatoryParameters: new RegulatoryParameterRepository,
        );
        $dto = new CalculationInputDTO(
            rawIbcPesos: 1_750_905,
            cotizationPeriod: new Periodo(2026, 3),
            contributorTypeCode: '57',
            arlRiskClass: 1,
        );

        $result = $service->calculate($dto);

        // Tipo 57 → IndependienteGeneralStrategy: S+P+ARL+CCF(2%)
        $this->assertSame(218_900, $result->subsystemAmountsPesos['health_total_pesos']);
        $this->assertSame(280_200, $result->subsystemAmountsPesos['pension_total_pesos']);
        $this->assertSame(9_200, $result->subsystemAmountsPesos['arl_total_pesos']);
        // CCF al 2% para independientes
        $this->assertSame(35_100, $result->subsystemAmountsPesos['ccf_total_pesos']);
        // Total = 218900+280200+9200+35100 = 543400
        $this->assertSame(543_400, $result->totalSocialSecurityPesos);
    }

    private function seedDefaultRates(): void
    {
        $params = [
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

        foreach ($params as [$category, $key, $value]) {
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
