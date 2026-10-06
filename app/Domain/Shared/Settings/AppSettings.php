<?php

namespace App\Domain\Shared\Settings;

use Illuminate\Support\Facades\DB;

/**
 * Parámetros editables del sistema. Si un valor no se configuró, se usa el de config/ (y .env).
 * Lectura memorizada por instancia: una consulta por proceso/petición.
 */
class AppSettings
{
    public const CHARGE_LEAD_DAYS = 'billing.charge_lead_days';

    /** @var array<string, mixed>|null */
    private ?array $values = null;

    public function chargeLeadDays(): int
    {
        return (int) ($this->get(self::CHARGE_LEAD_DAYS) ?? config('billing.charge_lead_days'));
    }

    public function get(string $key): mixed
    {
        $this->values ??= DB::table('app_settings')->pluck('value', 'key')->map(fn (string $value) => json_decode($value, true))->all();

        return $this->values[$key] ?? null;
    }

    public function set(string $key, mixed $value): void
    {
        DB::table('app_settings')->updateOrInsert(['key' => $key], ['value' => json_encode($value), 'updated_at' => now(), 'created_at' => now()]);
        $this->values = null;
    }
}
