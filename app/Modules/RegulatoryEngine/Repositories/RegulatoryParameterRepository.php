<?php

namespace App\Modules\RegulatoryEngine\Repositories;

use App\Modules\RegulatoryEngine\Models\RegulatoryParameter;
use DateTimeInterface;
use Illuminate\Support\Carbon;

final class RegulatoryParameterRepository
{
    /**
     * Registro vigente para categoría/clave en una fecha (mayor valid_from si hay solapamientos).
     */
    public function firstEffectiveAt(string $category, string $key, DateTimeInterface|string $on): ?RegulatoryParameter
    {
        $date = Carbon::parse($on)->toDateString();

        return RegulatoryParameter::query()
            ->where('category', $category)
            ->where('key', $key)
            ->whereDate('valid_from', '<=', $date)
            ->where(function ($q) use ($date): void {
                $q->whereNull('valid_until')
                    ->orWhereDate('valid_until', '>=', $date);
            })
            ->orderByDesc('valid_from')
            ->first();
    }

    public function valueAt(string $category, string $key, DateTimeInterface|string $on): ?string
    {
        return $this->firstEffectiveAt($category, $key, $on)?->value;
    }
}
