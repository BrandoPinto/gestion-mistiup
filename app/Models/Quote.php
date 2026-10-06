<?php

namespace App\Models;

use App\Domain\Quotes\Enums\QuoteStatus;
use App\Domain\Shared\Dates\DateOnly;
use App\Domain\Shared\Money\Currency;
use App\Domain\Shared\Money\MoneyCast;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Cotización. Se escribe solo vía Actions de App\Domain\Quotes. Los importes los calcula QuoteCalculator.
 */
class Quote extends Model
{
    use SoftDeletes;

    protected $guarded = ['*'];

    protected $hidden = ['public_token'];

    protected function casts(): array
    {
        return [
            'status' => QuoteStatus::class,
            'currency' => Currency::class,
            'issue_date' => DateOnly::class,
            'valid_until' => DateOnly::class,
            'delivery_date' => DateOnly::class,
            'tax_rate' => 'decimal:2',
            'global_discount_value' => 'decimal:2',
            'subtotal' => MoneyCast::class.':currency',
            'global_discount_amount' => MoneyCast::class.':currency',
            'discount_total' => MoneyCast::class.':currency',
            'total' => MoneyCast::class.':currency',
            'tax_base' => MoneyCast::class.':currency',
            'tax_amount' => MoneyCast::class.':currency',
            'public_enabled' => 'boolean',
            'first_viewed_at' => 'immutable_datetime',
            'last_viewed_at' => 'immutable_datetime',
            'view_count' => 'integer',
            'sent_at' => 'immutable_datetime',
            'converted_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuoteItem::class)->orderBy('position');
    }

    public function activityLogs(): MorphMany
    {
        return $this->morphMany(ActivityLog::class, 'subject');
    }

    public function isExpired(CarbonImmutable $today): bool
    {
        return $this->status->isEditable() && $this->valid_until->lessThan($today);
    }

    /**
     * Estado para mostrar. Calculados: "expired" (validez vencida) y "viewed" (abierta por el cliente).
     */
    public function displayStatus(CarbonImmutable $today): string
    {
        return match (true) {
            $this->isExpired($today) => 'expired',
            $this->status->isEditable() && $this->first_viewed_at !== null => 'viewed',
            default => $this->status->value,
        };
    }

    public function publicUrl(): string
    {
        return route('quotes.public', $this->public_token);
    }

    public function pdfFilename(): string
    {
        return $this->number.'.pdf';
    }

    /** @param Builder<Quote> $query */
    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', QuoteStatus::openValues());
    }
}
