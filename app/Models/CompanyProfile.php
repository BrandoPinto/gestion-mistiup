<?php

namespace App\Models;

use App\Domain\Shared\Money\Currency;
use Illuminate\Database\Eloquent\Model;

/**
 * Datos de la empresa emisora. Hay una sola fila (la crea la migración); usar CompanyProfile::current().
 */
class CompanyProfile extends Model
{
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'bank_accounts' => 'array',
            'default_tax_rate' => 'string',
            'default_currency' => Currency::class,
            'quote_validity_days' => 'integer',
        ];
    }

    public static function current(): self
    {
        return self::query()->firstOrFail();
    }

    public function displayName(): string
    {
        return $this->trade_name ?: ($this->legal_name ?: config('app.name'));
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path ? route('branding.logo', ['v' => substr(md5($this->logo_path), 0, 8)]) : null;
    }
}
