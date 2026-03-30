<?php

namespace App\Modules\PILALiquidation\Services;

use App\Modules\PILALiquidation\Enums\PilaLiquidationStatus;
use App\Modules\PILALiquidation\Models\PilaLiquidation;
use InvalidArgumentException;

final class PilaLiquidationStateService
{
    public function confirm(PilaLiquidation $liquidation): PilaLiquidation
    {
        if ($liquidation->status !== PilaLiquidationStatus::Draft) {
            throw new InvalidArgumentException('Solo se puede confirmar una liquidación en estado borrador.');
        }

        $liquidation->update(['status' => PilaLiquidationStatus::Confirmed]);

        return $liquidation->fresh()->load('lines', 'affiliate.person');
    }

    public function cancel(PilaLiquidation $liquidation): PilaLiquidation
    {
        if ($liquidation->status === PilaLiquidationStatus::Cancelled) {
            throw new InvalidArgumentException('La liquidación ya está cancelada.');
        }

        $liquidation->update(['status' => PilaLiquidationStatus::Cancelled]);

        return $liquidation->fresh()->load('lines', 'affiliate.person');
    }
}
