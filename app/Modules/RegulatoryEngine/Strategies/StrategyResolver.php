<?php

namespace App\Modules\RegulatoryEngine\Strategies;

use InvalidArgumentException;

/**
 * Resuelve la Strategy de cálculo según el código de tipo de cotizante.
 *
 * @see DOCUMENTO_RECTOR §4.2, RF-042
 */
final class StrategyResolver
{
    /** @var list<ContributorCalculationStrategy> */
    private readonly array $strategies;

    public function __construct()
    {
        $this->strategies = [
            new DependienteGeneralStrategy,
            new IndependienteGeneralStrategy,
            new TiempoParcialSubsidiadoStrategy,
            new ContratistaPSStrategy,
            new BeneficiarioUPCStrategy,
        ];
    }

    public function resolve(string $contributorTypeCode): ContributorCalculationStrategy
    {
        foreach ($this->strategies as $strategy) {
            if (in_array($contributorTypeCode, $strategy->supportedCodes(), true)) {
                return $strategy;
            }
        }

        throw new InvalidArgumentException(
            "No hay Strategy de cálculo para tipo cotizante '{$contributorTypeCode}'. "
            .'Tipos soportados: 01, 02, 03, 16, 40, 51, 57, 59.'
        );
    }

    /**
     * ¿El tipo de cotizante paga en período ACTUAL (independiente) o VENCIDO (dependiente)?
     */
    public function isCurrentPeriod(string $contributorTypeCode): bool
    {
        return $this->resolve($contributorTypeCode)->isCurrentPeriod();
    }
}
