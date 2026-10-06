<?php

namespace App\Domain\Billing;

use App\Domain\Billing\Enums\IntervalUnit;
use InvalidArgumentException;

/**
 * Frecuencia de cobro con UNA sola representación posible:
 * todo múltiplo de 12 meses se guarda en años (12 meses → 1 año, 24 meses → 2 años)
 * y "month" nunca lleva un count múltiplo de 12.
 */
final class Recurrence
{
    public const MAX_MONTHS = 120;

    private function __construct(
        public readonly IntervalUnit $unit,
        public readonly int $count,
    ) {}

    /** Única forma de construir una recurrencia: siempre sale normalizada. */
    public static function of(IntervalUnit $unit, int $count): self
    {
        $months = $unit === IntervalUnit::Year ? $count * 12 : $count;

        if ($months < 1 || $months > self::MAX_MONTHS) {
            throw new InvalidArgumentException('La frecuencia debe estar entre 1 mes y 10 años.');
        }

        return $months % 12 === 0
            ? new self(IntervalUnit::Year, intdiv($months, 12))
            : new self(IntervalUnit::Month, $months);
    }

    public function months(): int
    {
        return $this->unit === IntervalUnit::Year ? $this->count * 12 : $this->count;
    }

    public function label(): string
    {
        return match ($this->months()) {
            1 => 'Mensual',
            3 => 'Trimestral',
            6 => 'Semestral',
            12 => 'Anual',
            default => $this->unit === IntervalUnit::Year
                ? "Cada {$this->count} años"
                : "Cada {$this->count} meses",
        };
    }

    public function equals(self $other): bool
    {
        return $this->unit === $other->unit && $this->count === $other->count;
    }
}
