<?php

namespace App\Modules\RegulatoryEngine\Services;

// DOCUMENTO_RECTOR §3.5 — escala Fondo de Solidaridad (cfg_solidarity_fund_scale + SMMLV en cfg_regulatory_parameters)

use App\Modules\RegulatoryEngine\Models\SolidarityFundScale;
use App\Modules\RegulatoryEngine\Repositories\RegulatoryParameterRepository;

final class SolidarityFundCalculator
{
    public function __construct(
        private readonly RegulatoryParameterRepository $regulatoryParameters,
    ) {}

    /**
     * Aporte adicional por fondo de solidaridad pensional sobre IBC redondeada.
     *
     * @return array{pesos: int, rate_percent: float|null, min_smmlv_bracket: float|null}
     */
    public function compute(int $ibcRoundedPesos, string $onDate): array
    {
        $smmlv = $this->smmlvPesos($onDate);
        if ($smmlv <= 0 || $ibcRoundedPesos <= 0) {
            return ['pesos' => 0, 'rate_percent' => null, 'min_smmlv_bracket' => null];
        }

        $ibcInSmmlv = $ibcRoundedPesos / $smmlv;

        $row = SolidarityFundScale::query()
            ->whereDate('valid_from', '<=', $onDate)
            ->where(function ($q) use ($onDate): void {
                $q->whereNull('valid_until')
                    ->orWhereDate('valid_until', '>=', $onDate);
            })
            ->where('min_smmlv', '<=', $ibcInSmmlv)
            ->orderByDesc('min_smmlv')
            ->first();

        if ($row === null) {
            return ['pesos' => 0, 'rate_percent' => null, 'min_smmlv_bracket' => null];
        }

        $rate = (float) $row->rate;
        $pesos = (int) round($ibcRoundedPesos * ($rate / 100));

        return [
            'pesos' => $pesos,
            'rate_percent' => $rate,
            'min_smmlv_bracket' => (float) $row->min_smmlv,
        ];
    }

    private function smmlvPesos(string $onDate): int
    {
        $v = $this->regulatoryParameters->valueAt('monetary', 'SMMLV', $onDate);
        if (! is_numeric($v)) {
            return 0;
        }

        return (int) $v;
    }
}
