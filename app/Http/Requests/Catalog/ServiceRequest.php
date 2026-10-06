<?php

namespace App\Http\Requests\Catalog;

use App\Domain\Billing\Enums\BillingType;
use App\Domain\Billing\Enums\IntervalUnit;
use App\Domain\Shared\Money\Currency;
use App\Models\Service;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ServiceRequest extends FormRequest
{
    /** Importe como texto decimal: hasta 10 enteros y 2 decimales, sin signo. */
    public const AMOUNT_PATTERN = '/^\d{1,10}(\.\d{1,2})?$/';

    public function authorize(): bool
    {
        $service = $this->route('service');

        return $service instanceof Service
            ? $this->user()->can('update', $service)
            : $this->user()->can('create', Service::class);
    }

    protected function prepareForValidation(): void
    {
        $price = is_string($this->input('default_price')) ? trim(str_replace(',', '', $this->input('default_price'))) : $this->input('default_price');
        $isOneTime = $this->input('default_billing_type') === BillingType::OneTime->value;

        $this->merge([
            'name' => is_string($this->input('name')) ? trim($this->input('name')) : $this->input('name'),
            'description' => is_string($this->input('description')) && trim($this->input('description')) !== '' ? trim($this->input('description')) : null,
            'default_price' => $price === '' ? null : $price,
            // Un pago único no tiene frecuencia: se descarta lo que venga.
            'default_interval_unit' => $isOneTime ? null : $this->input('default_interval_unit'),
            'default_interval_count' => $isOneTime ? null : $this->input('default_interval_count'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $recurring = Rule::requiredIf(fn () => $this->input('default_billing_type') === BillingType::Recurring->value);

        return [
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'default_currency' => ['required', Rule::enum(Currency::class)],
            'default_price' => ['nullable', 'string', 'regex:'.self::AMOUNT_PATTERN],
            'default_billing_type' => ['required', Rule::enum(BillingType::class)],
            'default_interval_unit' => [$recurring, 'nullable', Rule::enum(IntervalUnit::class)],
            'default_interval_count' => [$recurring, 'nullable', 'integer', 'min:1'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty() || $this->input('default_billing_type') !== BillingType::Recurring->value) {
                    return;
                }

                $months = (int) $this->input('default_interval_count') * ($this->input('default_interval_unit') === IntervalUnit::Year->value ? 12 : 1);

                if ($months > 120) {
                    $validator->errors()->add('default_interval_count', 'La frecuencia no puede superar 10 años.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'default_price.regex' => 'Ingresa un importe válido, por ejemplo 350 o 350.50 (máximo 2 decimales).',
            'default_interval_unit.required' => 'Elige la frecuencia del cobro.',
            'default_interval_count.required' => 'Indica cada cuánto se cobra.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'description' => 'descripción',
            'default_currency' => 'moneda',
            'default_price' => 'precio sugerido',
            'default_billing_type' => 'modalidad',
            'default_interval_unit' => 'unidad de frecuencia',
            'default_interval_count' => 'frecuencia',
            'is_active' => 'estado',
        ];
    }
}
