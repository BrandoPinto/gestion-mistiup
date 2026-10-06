<?php

namespace App\Models;

use App\Domain\Billing\Enums\ChargeStatus;
use App\Domain\Shared\Dates\DateOnly;
use App\Domain\Shared\Money\Currency;
use App\Domain\Shared\Money\MoneyCast;
use Brick\Money\Money;
use Carbon\CarbonImmutable;
use Database\Factories\ChargeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Cobro: dinero que se debe recibir. Se escribe solo vía Actions de App\Domain\Billing.
 * amount_paid y status son derivados de los pagos y los mantiene ChargeSettlement.
 */
class Charge extends Model
{
    /** @use HasFactory<ChargeFactory> */
    use HasFactory;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'currency' => Currency::class,
            'amount' => MoneyCast::class.':currency',
            'amount_paid' => MoneyCast::class.':currency',
            'tax_amount' => MoneyCast::class.':currency',
            'tax_rate' => 'string',
            'status' => ChargeStatus::class,
            'due_date' => DateOnly::class,
            'period_start' => DateOnly::class,
            'period_end' => DateOnly::class,
            'paid_on' => DateOnly::class,
            'cycle_number' => 'integer',
            'cancelled_at' => 'immutable_datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(ClientService::class, 'client_service_id')->withTrashed();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function activityLogs(): MorphMany
    {
        return $this->morphMany(ActivityLog::class, 'subject');
    }

    public function balance(): Money
    {
        return $this->amount->minus($this->amount_paid);
    }

    public function isOverdue(CarbonImmutable $today): bool
    {
        return $this->status->isOpen() && $this->due_date->lessThan($today);
    }

    /** @param Builder<Charge> $query */
    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', ChargeStatus::openValues());
    }

    /** @param Builder<Charge> $query */
    public function scopeOverdue(Builder $query, CarbonImmutable $today): void
    {
        $query->open()->where('due_date', '<', $today->toDateString());
    }
}
