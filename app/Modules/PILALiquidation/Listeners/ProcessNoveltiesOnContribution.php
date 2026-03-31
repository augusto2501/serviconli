<?php

namespace App\Modules\PILALiquidation\Listeners;

use App\Modules\Affiliates\Services\NoveltyService;
use App\Modules\PILALiquidation\Events\ContributionSaved;
use App\Modules\RegulatoryEngine\ValueObjects\Periodo;

/**
 * Procesa novedades post-guardado del aporte.
 *
 * TAE/TAP → versiona perfil SS.
 * VSP → actualiza IBC.
 * RET → cambia estado según tipo X/P/R.
 *
 * @see DOCUMENTO_RECTOR §3.4, RF-061..RF-066
 */
final class ProcessNoveltiesOnContribution
{
    public function __construct(
        private readonly NoveltyService $noveltyService,
    ) {}

    public function handle(ContributionSaved $event): void
    {
        if (empty($event->novelties)) {
            return;
        }

        $period = new Periodo(
            (int) $event->liquidation->lines->first()?->period_year ?? now()->year,
            (int) $event->liquidation->lines->first()?->period_month ?? now()->month,
        );

        foreach ($event->novelties as $noveltyData) {
            $this->noveltyService->register(
                $event->affiliate,
                $period,
                $noveltyData['type_code'],
                $noveltyData,
            );
        }
    }
}
