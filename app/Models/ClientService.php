<?php

namespace App\Models;

use App\Domain\Billing\Enums\BillingType;
use App\Domain\Billing\Enums\IntervalUnit;
use App\Domain\Billing\Recurrence;
use App\Domain\Contracts\Enums\ContractStatus;
use App\Domain\Shared\Dates\DateOnly;
use App\Domain\Shared\Money\Currency;
use App\Domain\Shared\Money\MoneyCast;
use Database\Factories\ClientServiceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Servicio contratado (contrato). Solo se escribe a través de las Actions de App\Domain\Contracts,
 * por eso no declara $fillable: todos los atributos se asignan explícitamente.
 */
class ClientService extends Model
{
    /** @use HasFactory<ClientServiceFactory> */
    use HasFactory, SoftDeletes;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'currency' => Currency::class,
            'price' => MoneyCast::class.':currency',
            'billing_type' => BillingType::class,
            'interval_unit' => IntervalUnit::class,
            'interval_count' => 'integer',
            'term_months' => 'integer',
            'start_date' => DateOnly::class,
            'end_date' => DateOnly::class,
            'next_cycle_number' => 'integer',
            'next_charge_date' => DateOnly::class,
            'status' => ContractStatus::class,
            'cancelled_at' => 'immutable_datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class)->withTrashed();
    }

    public function quoteItem(): BelongsTo
    {
        return $this->belongsTo(QuoteItem::class);
    }

    public function charges(): HasMany
    {
        return $this->hasMany(Charge::class);
    }

    /**
     * Con cobros generados, el calendario (inicio, frecuencia, duración, moneda, ciclo) queda fijo:
     * cambiarlo dejaría cobros existentes fuera de su ciclo.
     */
    public function scheduleLocked(): bool
    {
        return $this->exists && $this->charges()->exists();
    }

    public function activityLogs(): MorphMany
    {
        return $this->morphMany(ActivityLog::class, 'subject');
    }

    public function isRecurring(): bool
    {
        return $this->billing_type === BillingType::Recurring;
    }

    public function recurrence(): ?Recurrence
    {
        return $this->isRecurring() ? Recurrence::of($this->interval_unit, $this->interval_count) : null;
    }

    /** Total de ciclos (null = sin fecha de fin). Un pago único tiene un solo ciclo. */
    public function totalCycles(): ?int
    {
        if (! $this->isRecurring()) {
            return 1;
        }

        return $this->term_months === null ? null : intdiv($this->term_months, $this->recurrence()->months());
    }

    public function billingLabel(): string
    {
        return $this->recurrence()?->label() ?? BillingType::OneTime->label();
    }

    /** @param Builder<ClientService> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', ContractStatus::Active->value);
    }
}
