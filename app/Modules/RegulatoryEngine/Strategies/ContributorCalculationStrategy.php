<?php

namespace App\Modules\RegulatoryEngine\Strategies;

use App\Modules\RegulatoryEngine\DTOs\CalculationContext;
use App\Modules\RegulatoryEngine\Enums\SubsystemType;

/**
 * Strategy de cálculo por tipo de cotizante.
 *
 * Cada implementación encapsula las reglas específicas de su grupo:
 * qué subsistemas aplican, cómo se calcula el IBC, particularidades.
 *
 * @see DOCUMENTO_RECTOR §2.2, §4.2, RF-042
 */
interface ContributorCalculationStrategy
{
    /**
     * Códigos de tipo cotizante que cubre esta Strategy.
     *
     * @return list<string>
     */
    public function supportedCodes(): array;

    /**
     * Subsistemas que aplican para este grupo de cotizantes.
     *
     * @return list<SubsystemType>
     */
    public function applicableSubsystems(): array;

    /**
     * Calcula los aportes por subsistema.
     *
     * @return array<string, int> Mapa subsistema → monto en pesos
     */
    public function calculate(CalculationContext $context): array;

    /**
     * ¿Este tipo cotizante paga en período ACTUAL (independiente) o VENCIDO (dependiente)?
     */
    public function isCurrentPeriod(): bool;
}
