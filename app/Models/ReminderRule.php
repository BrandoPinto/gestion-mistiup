<?php

namespace App\Models;

use App\Domain\Reminders\Enums\ReminderEvent;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['event', 'days', 'is_active'])]
class ReminderRule extends Model
{
    protected function casts(): array
    {
        return [
            'event' => ReminderEvent::class,
            'days' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @param Builder<ReminderRule> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
