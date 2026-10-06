<?php

namespace App\Domain\Reminders\Enums;

enum ReminderEvent: string
{
    case ChargeDue = 'charge_due';
    case ChargeOverdue = 'charge_overdue';
    case ContractEnd = 'contract_end';

    public function label(): string
    {
        return match ($this) {
            self::ChargeDue => 'Antes del vencimiento de un cobro',
            self::ChargeOverdue => 'Después de que un cobro vence',
            self::ContractEnd => 'Antes del fin de un contrato',
        };
    }

    /** Cómo se lee la cantidad de días en esta regla. */
    public function daysLabel(int $days): string
    {
        $unit = $days === 1 ? 'día' : 'días';

        return match ($this) {
            self::ChargeDue, self::ContractEnd => "{$days} {$unit} antes",
            self::ChargeOverdue => "{$days} {$unit} después",
        };
    }
}
