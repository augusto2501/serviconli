<?php

namespace Tests\Feature\RegulatoryEngine;

use App\Modules\RegulatoryEngine\DTOs\CalculationInputDTO;
use App\Modules\RegulatoryEngine\Enums\ExceptionType;
use App\Modules\RegulatoryEngine\Enums\SubsystemType;
use App\Modules\RegulatoryEngine\Models\ContributorType;
use App\Modules\RegulatoryEngine\Models\ContributorTypeSubsystem;
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
        $this->assertSame(0.031, $result->subsystemAmountsPesos['mora_rate_percent']);
        $this->assertSame(0, $result->subsystemAmountsPesos['mora_interest_pesos']);
        $this->assertSame(578_215, $result->totalSocialSecurityPesos);
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
        $this->assertSame(0.05, $result->subsystemAmountsPesos['mora_rate_percent']);
        $this->assertSame(8_755, $result->subsystemAmountsPesos['mora_interest_pesos']);
        $this->assertSame(586_970, $result->totalSocialSecurityPesos);
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

        $this->assertSame(227_630, $result->subsystemAmountsPesos['health_total_pesos']);
        $this->assertSame(297_670, $result->subsystemAmountsPesos['pension_total_pesos']);
        $this->assertSame(17_510, $result->subsystemAmountsPesos['arl_total_pesos']);
        $this->assertSame(87_550, $result->subsystemAmountsPesos['ccf_total_pesos']);
        $this->assertSame(17_510, $result->subsystemAmountsPesos['mora_interest_pesos']);
        $this->assertSame(647_870, $result->totalSocialSecurityPesos);
    }

    public function test_calculation_respects_subsystem_configuration_for_contributor_type(): void
    {
        $this->seedDefaultRates();

        $type = ContributorType::query()->create([
            'code' => '57',
            'name' => 'Contratista',
            'is_active' => true,
        ]);

        ContributorTypeSubsystem::query()->create([
            'contributor_type_id' => $type->id,
            'subsystem' => SubsystemType::SALUD->value,
            'is_required' => true,
        ]);
        ContributorTypeSubsystem::query()->create([
            'contributor_type_id' => $type->id,
            'subsystem' => SubsystemType::PENSION->value,
            'is_required' => false,
        ]);
        ContributorTypeSubsystem::query()->create([
            'contributor_type_id' => $type->id,
            'subsystem' => SubsystemType::ARL->value,
            'is_required' => true,
        ]);
        ContributorTypeSubsystem::query()->create([
            'contributor_type_id' => $type->id,
            'subsystem' => SubsystemType::CCF->value,
            'is_required' => false,
        ]);

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

        $this->assertSame(218_875, $result->subsystemAmountsPesos['health_total_pesos']);
        $this->assertSame(0, $result->subsystemAmountsPesos['pension_total_pesos']);
        $this->assertSame(9_140, $result->subsystemAmountsPesos['arl_total_pesos']);
        $this->assertSame(0, $result->subsystemAmountsPesos['ccf_total_pesos']);
        $this->assertSame(228_015, $result->totalSocialSecurityPesos);
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
