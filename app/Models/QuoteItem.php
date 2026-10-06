<?php

namespace App\Models;

use App\Domain\Billing\Enums\BillingType;
use App\Domain\Billing\Enums\IntervalUnit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Concepto de una cotización. Importes como texto decimal (la moneda vive en la cotización).
 */
class QuoteItem extends Model
{
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'discount_value' => 'decimal:2',
            'line_gross' => 'decimal:2',
            'line_discount' => 'decimal:2',
            'line_total' => 'decimal:2',
            'suggested_billing_type' => BillingType::class,
            'suggested_interval_unit' => IntervalUnit::class,
            'suggested_interval_count' => 'integer',
        ];
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    /** Contrato creado a partir de este concepto (si ya se convirtió). */
    public function contract(): HasOne
    {
        return $this->hasOne(ClientService::class)->withTrashed();
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class)->withTrashed();
    }
}
