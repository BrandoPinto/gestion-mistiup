<?php

namespace App\Models;

use App\Domain\Shared\Dates\DateOnly;
use App\Domain\Shared\Money\Currency;
use App\Domain\Shared\Money\MoneyCast;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Pago: dinero realmente recibido. Nunca se borra; se anula (voided_at). Se escribe solo vía Actions.
 */
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'currency' => Currency::class,
            'amount' => MoneyCast::class.':currency',
            'paid_on' => DateOnly::class,
            'voided_at' => 'immutable_datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    public function charge(): BelongsTo
    {
        return $this->belongsTo(Charge::class);
    }

    public function method(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    /** @param Builder<Payment> $query */
    public function scopeValid(Builder $query): void
    {
        $query->whereNull('voided_at');
    }
}
