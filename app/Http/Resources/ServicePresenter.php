<?php

namespace App\Http\Resources;

use App\Domain\Shared\Money\MoneyPresenter;
use App\Models\Service;

/**
 * Debe coincidir con resources/js/features/catalog/types.ts.
 */
final class ServicePresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function item(Service $service): array
    {
        return [
            'id' => $service->id,
            'name' => $service->name,
            'description' => $service->description,
            'default_currency' => $service->default_currency->value,
            'default_price' => $service->default_price ? MoneyPresenter::toArray($service->default_price) : null,
            'default_billing_type' => $service->default_billing_type->value,
            'default_interval_unit' => $service->default_interval_unit?->value,
            'default_interval_count' => $service->default_interval_count,
            'billing_label' => $service->billingLabel(),
            'is_active' => $service->is_active,
        ];
    }
}
