<?php

namespace App\Modules\RegulatoryEngine\Services;

use App\Modules\RegulatoryEngine\Enums\ExceptionType;
use App\Modules\RegulatoryEngine\Models\OperationalException;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Support\Collection;

final class OperationalExceptionService
{
    /**
     * @return Collection<int, OperationalException>
     */
    public function activeForTarget(string $targetType, int $targetId, DateTimeInterface|string $on): Collection
    {
        $date = Carbon::parse($on)->toDateString();

        return OperationalException::query()
            ->where('is_active', true)
            ->where('target_type', $targetType)
            ->where('target_id', $targetId)
            ->where('valid_from', '<=', $date)
            ->where(function ($q) use ($date): void {
                $q->whereNull('valid_until')
                    ->orWhere('valid_until', '>=', $date);
            })
            ->orderByDesc('valid_from')
            ->get();
    }

    public function firstByType(string $targetType, int $targetId, ExceptionType $type, DateTimeInterface|string $on): ?OperationalException
    {
        return $this->activeForTarget($targetType, $targetId, $on)
            ->first(static fn (OperationalException $e): bool => $e->exception_type === $type->value);
    }

    public function isMoraExempt(string $targetType, int $targetId, DateTimeInterface|string $on): bool
    {
        return $this->firstByType($targetType, $targetId, ExceptionType::MORA_EXEMPT, $on) !== null;
    }

    public function moraRateOverridePercent(string $targetType, int $targetId, DateTimeInterface|string $on): ?float
    {
        $exception = $this->firstByType($targetType, $targetId, ExceptionType::MORA_RATE_OVERRIDE, $on);

        if ($exception === null) {
            return null;
        }

        $rate = $exception->value['rate_percent'] ?? null;

        return is_numeric($rate) ? (float) $rate : null;
    }
}
