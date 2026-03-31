<?php

namespace App\Modules\RegulatoryEngine\ValueObjects;

use Carbon\Carbon;

/**
 * Período de cotización (año + mes).
 *
 * @see DOCUMENTO_RECTOR §2.3
 */
final readonly class Periodo
{
    public function __construct(
        public int $year,
        public int $month,
    ) {
        if ($year < 1970 || $year > 2100) {
            throw new \InvalidArgumentException('Año fuera de rango.');
        }
        if ($month < 1 || $month > 12) {
            throw new \InvalidArgumentException('Mes inválido.');
        }
    }

    public static function fromDate(Carbon $date): self
    {
        return new self($date->year, $date->month);
    }

    public static function fromString(string $yyyymm): self
    {
        if (! preg_match('/^(\d{4})-?(\d{2})$/', $yyyymm, $m)) {
            throw new \InvalidArgumentException("Formato período inválido: {$yyyymm}. Esperado YYYYMM o YYYY-MM.");
        }

        return new self((int) $m[1], (int) $m[2]);
    }

    /** Portado de Access: período siguiente. */
    public function siguiente(): self
    {
        return $this->month === 12
            ? new self($this->year + 1, 1)
            : new self($this->year, $this->month + 1);
    }

    /** Período anterior. */
    public function anterior(): self
    {
        return $this->month === 1
            ? new self($this->year - 1, 12)
            : new self($this->year, $this->month - 1);
    }

    public function format(): string
    {
        return sprintf('%04d-%02d', $this->year, $this->month);
    }

    public function toCarbon(): Carbon
    {
        return Carbon::create($this->year, $this->month, 1)->startOfDay();
    }

    public function equals(self $other): bool
    {
        return $this->year === $other->year && $this->month === $other->month;
    }

    public function isAfter(self $other): bool
    {
        return $this->year > $other->year
            || ($this->year === $other->year && $this->month > $other->month);
    }

    public function isBefore(self $other): bool
    {
        return $other->isAfter($this);
    }
}
