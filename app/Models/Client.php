<?php

namespace App\Models;

use App\Domain\Clients\Enums\ClientStatus;
use App\Domain\Clients\Enums\ClientType;
use App\Domain\Clients\Enums\DocumentType;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'type', 'name', 'trade_name', 'document_type', 'document_number',
    'phone', 'whatsapp', 'email', 'address', 'contact_name', 'notes', 'status',
])]
class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use HasFactory, SoftDeletes;

    protected $attributes = [
        'type' => 'company',
        'document_type' => 'NONE',
        'status' => 'active',
    ];

    protected function casts(): array
    {
        return [
            'type' => ClientType::class,
            'document_type' => DocumentType::class,
            'status' => ClientStatus::class,
        ];
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(ClientService::class);
    }

    public function charges(): HasMany
    {
        return $this->hasMany(Charge::class);
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function activityLogs(): MorphMany
    {
        return $this->morphMany(ActivityLog::class, 'subject');
    }

    /** @param Builder<Client> $query */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term === '') {
            return;
        }

        // Escapa comodines de LIKE para que "%" o "_" escritos por el usuario se busquen literalmente.
        $like = '%'.addcslashes($term, '%_\\').'%';

        $query->where(function (Builder $inner) use ($like) {
            $inner->where('name', 'like', $like)
                ->orWhere('trade_name', 'like', $like)
                ->orWhere('document_number', 'like', $like)
                ->orWhere('email', 'like', $like)
                ->orWhere('contact_name', 'like', $like)
                ->orWhere('phone', 'like', $like)
                ->orWhere('whatsapp', 'like', $like);
        });
    }

    public function displayDocument(): ?string
    {
        if ($this->document_type === DocumentType::None || ! $this->document_number) {
            return null;
        }

        return $this->document_type->value.' '.$this->document_number;
    }
}
