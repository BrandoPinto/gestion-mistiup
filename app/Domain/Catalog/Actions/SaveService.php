<?php

namespace App\Domain\Catalog\Actions;

use App\Domain\Billing\Enums\BillingType;
use App\Domain\Billing\Enums\IntervalUnit;
use App\Domain\Billing\Recurrence;
use App\Domain\Shared\Audit\ActivityLogger;
use App\Domain\Shared\Audit\ModelChanges;
use App\Models\Service;
use Illuminate\Support\Facades\DB;

/**
 * Crea o actualiza un servicio del catálogo. La recurrencia siempre se guarda normalizada.
 */
class SaveService
{
    public function __construct(private readonly ActivityLogger $logger) {}

    /**
     * @param  array<string, mixed>  $data  Datos validados por ServiceRequest.
     */
    public function handle(array $data, ?Service $service = null): Service
    {
        return DB::transaction(function () use ($data, $service) {
            $isNew = $service === null;
            $service ??= new Service;

            // La moneda primero: el precio se interpreta en esa moneda (MoneyCast).
            $service->default_currency = $data['default_currency'];
            $service->fill([
                'name' => $data['name'],
                'description' => $data['description'],
                'default_price' => $data['default_price'],
                'is_active' => $data['is_active'],
                ...$this->recurrenceAttributes($data),
            ]);

            $changes = $isNew ? [] : ModelChanges::pending($service);
            $service->save();

            if ($isNew) {
                $this->logger->log('service.created', $service);
            } elseif ($changes !== []) {
                $this->logger->log('service.updated', $service, ['changes' => $changes]);
            }

            return $service;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{default_billing_type: string, default_interval_unit: ?string, default_interval_count: ?int}
     */
    private function recurrenceAttributes(array $data): array
    {
        if ($data['default_billing_type'] !== BillingType::Recurring->value) {
            return [
                'default_billing_type' => BillingType::OneTime->value,
                'default_interval_unit' => null,
                'default_interval_count' => null,
            ];
        }

        $recurrence = Recurrence::of(IntervalUnit::from($data['default_interval_unit']), (int) $data['default_interval_count']);

        return [
            'default_billing_type' => BillingType::Recurring->value,
            'default_interval_unit' => $recurrence->unit->value,
            'default_interval_count' => $recurrence->count,
        ];
    }
}
