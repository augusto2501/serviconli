<?php

namespace App\Modules\CashReconciliation\Services;

use App\Modules\CashReconciliation\Models\DailyClose;
use App\Modules\CashReconciliation\Models\DailyReconciliation;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Cierre fin de día — 13 conceptos consolidados, DOCUMENTO_RECTOR §8.2.
 */
final class DailyCloseService
{
    public function __construct(
        private readonly DailyReconciliationService $reconciliationService,
    ) {}

    /**
     * @param  array<string, int>|null  $conceptOverrides  Reemplaza valores por clave antes de guardar
     */
    public function close(
        DailyReconciliation $reconciliation,
        ?int $userId = null,
        ?array $conceptOverrides = null,
    ): DailyClose {
        if ($reconciliation->isClosed()) {
            throw new InvalidArgumentException('El cuadre del día ya está cerrado.');
        }

        $this->reconciliationService->recalculate($reconciliation);

        $base = $this->reconciliationService->defaultThirteenConcepts($reconciliation);
        if ($conceptOverrides !== null) {
            $base = array_merge($base, $conceptOverrides);
        }

        $grand = $this->reconciliationService->sumConcepts($base);

        return DB::transaction(function () use ($reconciliation, $userId, $base, $grand): DailyClose {
            $reconciliation->update([
                'status' => 'CERRADO',
                'closed_at' => now(),
                'user_id' => $userId ?? $reconciliation->user_id,
            ]);

            return DailyClose::query()->updateOrCreate(
                ['reconciliation_id' => $reconciliation->id],
                [
                    'user_id' => $userId,
                    'closed_at' => now(),
                    'concept_amounts' => $base,
                    'grand_total_pesos' => $grand,
                ]
            );
        });
    }
}
