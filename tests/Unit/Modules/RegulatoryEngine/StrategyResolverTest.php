<?php

namespace Tests\Unit\Modules\RegulatoryEngine;

use App\Modules\RegulatoryEngine\DTOs\CalculationContext;
use App\Modules\RegulatoryEngine\Strategies\BeneficiarioUPCStrategy;
use App\Modules\RegulatoryEngine\Strategies\ContratistaPSStrategy;
use App\Modules\RegulatoryEngine\Strategies\DependienteGeneralStrategy;
use App\Modules\RegulatoryEngine\Strategies\IndependienteGeneralStrategy;
use App\Modules\RegulatoryEngine\Strategies\StrategyResolver;
use App\Modules\RegulatoryEngine\Strategies\TiempoParcialSubsidiadoStrategy;
use App\Modules\RegulatoryEngine\ValueObjects\Periodo;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class StrategyResolverTest extends TestCase
{
    private StrategyResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new StrategyResolver;
    }

    public function test_resolves_dependiente_for_type_01(): void
    {
        $strategy = $this->resolver->resolve('01');
        $this->assertInstanceOf(DependienteGeneralStrategy::class, $strategy);
        $this->assertFalse($strategy->isCurrentPeriod());
    }

    public function test_resolves_dependiente_for_type_02(): void
    {
        $strategy = $this->resolver->resolve('02');
        $this->assertInstanceOf(DependienteGeneralStrategy::class, $strategy);
    }

    public function test_resolves_independiente_for_type_03(): void
    {
        $strategy = $this->resolver->resolve('03');
        $this->assertInstanceOf(IndependienteGeneralStrategy::class, $strategy);
        $this->assertTrue($strategy->isCurrentPeriod());
    }

    public function test_resolves_independiente_for_type_57(): void
    {
        $strategy = $this->resolver->resolve('57');
        $this->assertInstanceOf(IndependienteGeneralStrategy::class, $strategy);
    }

    public function test_resolves_tiempo_parcial_for_type_51(): void
    {
        $strategy = $this->resolver->resolve('51');
        $this->assertInstanceOf(TiempoParcialSubsidiadoStrategy::class, $strategy);
        $this->assertFalse($strategy->isCurrentPeriod());
    }

    public function test_resolves_contratista_for_type_59(): void
    {
        $strategy = $this->resolver->resolve('59');
        $this->assertInstanceOf(ContratistaPSStrategy::class, $strategy);
        $this->assertTrue($strategy->isCurrentPeriod());
    }

    public function test_resolves_beneficiario_upc_for_type_40(): void
    {
        $strategy = $this->resolver->resolve('40');
        $this->assertInstanceOf(BeneficiarioUPCStrategy::class, $strategy);
    }

    public function test_throws_for_unknown_type(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->resolver->resolve('99');
    }

    #[DataProvider('rn_02_tipo_51_semanas_data')]
    public function test_rn_02_tipo_51_pension_por_semanas(int $days, int $expectedWeeks, int $expectedPensionIbc): void
    {
        $strategy = $this->resolver->resolve('51');
        $context = new CalculationContext(
            salaryPesos: 1_423_500,
            daysEps: $days,
            daysAfp: $days,
            daysArl: $days,
            daysCcf: 0,
            cotizationPeriod: new Periodo(2026, 3),
            contributorTypeCode: '51',
            isType51: true,
            healthRatePercent: 12.5,
            pensionRatePercent: 16.0,
            arlRatePercent: 0.522,
            ccfRatePercent: 0,
        );

        $result = $strategy->calculate($context);

        $this->assertSame($expectedWeeks, $result['pension_weeks']);
        $this->assertSame($expectedPensionIbc, $result['ibc_pension_pesos']);
    }

    public static function rn_02_tipo_51_semanas_data(): array
    {
        // Salario 1,423,500 → Salario/4 = 355,875
        // IBC pensión = roundIBC(355875 × weeks)
        return [
            '7 días = 1 semana' => [7, 1, 356_000],   // roundIBC(355875) = 356000
            '14 días = 2 semanas' => [14, 2, 712_000],  // roundIBC(711750) = 712000
            '21 días = 3 semanas' => [21, 3, 1_068_000], // roundIBC(1067625) = 1068000
            '30 días = 4 semanas' => [30, 4, 1_424_000], // roundIBC(1423500) = 1424000
        ];
    }

    public function test_rn_contratista_59_no_arl_no_ccf(): void
    {
        $strategy = $this->resolver->resolve('59');
        $context = new CalculationContext(
            salaryPesos: 1_423_500,
            daysEps: 30,
            daysAfp: 30,
            daysArl: 30,
            daysCcf: 30,
            cotizationPeriod: new Periodo(2026, 3),
            contributorTypeCode: '59',
            healthRatePercent: 12.5,
            pensionRatePercent: 16.0,
            arlRatePercent: 0.522,
            ccfRatePercent: 2.0,
        );

        $result = $strategy->calculate($context);

        $this->assertSame(0, $result['arl_total_pesos']);
        $this->assertSame(0, $result['ccf_total_pesos']);
        $this->assertGreaterThan(0, $result['health_total_pesos']);
        $this->assertGreaterThan(0, $result['pension_total_pesos']);
    }

    public function test_rn_beneficiario_upc_only_health(): void
    {
        $strategy = $this->resolver->resolve('40');
        $context = new CalculationContext(
            salaryPesos: 1_423_500,
            daysEps: 30,
            daysAfp: 30,
            daysArl: 30,
            daysCcf: 30,
            cotizationPeriod: new Periodo(2026, 3),
            contributorTypeCode: '40',
            healthRatePercent: 12.5,
            pensionRatePercent: 16.0,
            arlRatePercent: 0.522,
            ccfRatePercent: 4.0,
        );

        $result = $strategy->calculate($context);

        $this->assertGreaterThan(0, $result['health_total_pesos']);
        $this->assertSame(0, $result['pension_total_pesos']);
        $this->assertSame(0, $result['arl_total_pesos']);
        $this->assertSame(0, $result['ccf_total_pesos']);
        $this->assertSame(0, $result['admin_fee_pesos']);
    }
}
