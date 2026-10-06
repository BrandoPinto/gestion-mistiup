<?php

namespace App\Models;

use App\Domain\Billing\Enums\BillingType;
use App\Domain\Billing\Enums\IntervalUnit;
use App\Domain\Billing\Recurrence;
use App\Domain\Shared\Money\Currency;
use App\Domain\Shared\Money\MoneyCast;
use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Servicio del catálogo (plantilla). El contrato real con un cliente es ClientService (Fase 4).
 */
#[Fillable([
    'name', 'description', 'default_currency', 'default_price',
    'default_billing_type', 'default_interval_unit', 'default_interval_count', 'is_active',
])]
class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use HasFactory, SoftDeletes;

    protected $attributes = [
        'default_currency' => 'PEN',
        'default_billing_type' => 'one_time',
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'default_currency' => Currency::class,
            'default_price' => MoneyCast::class.':default_currency',
            'default_billing_type' => BillingType::class,
            'default_interval_unit' => IntervalUnit::class,
            'default_interval_count' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function defaultRecurrence(): ?Recurrence
    {
        if ($this->default_billing_type !== BillingType::Recurring) {
            return null;
        }

        return Recurrence::of($this->default_interval_unit, $this->default_interval_count);
    }

    public function billingLabel(): string
    {
        return $this->defaultRecurrence()?->label() ?? BillingType::OneTime->label();
    }

    /** @param Builder<Service> $query */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term === '') {
            return;
        }

        $like = '%'.addcslashes($term, '%_\\').'%';

        $query->where(fn (Builder $inner) => $inner->where('name', 'like', $like)->orWhere('description', 'like', $like));
    }

    /** @param Builder<Service> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
