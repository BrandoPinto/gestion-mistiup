<?php

namespace App\Http\Requests\Quotes;

use App\Domain\Quotes\QuoteCalculationException;
use App\Domain\Quotes\Services\QuoteCalculator;
use App\Domain\Shared\Money\Currency;
use App\Http\Requests\Catalog\ServiceRequest;
use App\Models\Quote;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Cotización completa (cabecera + conceptos). Además del formato, ejecuta QuoteCalculator para rechazar
 * descuentos imposibles con el mensaje en el campo exacto.
 */
class QuoteRequest extends FormRequest
{
    private const QUANTITY_PATTERN = '/^\d{1,6}(\.\d{1,2})?$/';

    private const PERCENT_OR_AMOUNT = ['percent', 'amount'];

    public function authorize(): bool
    {
        $quote = $this->route('quote');

        return $quote instanceof Quote
            ? $this->user()->can('update', $quote)
            : $this->user()->can('create', Quote::class);
    }

    protected function prepareForValidation(): void
    {
        // Ojo: no usar "?: null" aquí; "0" es un valor válido (IGV 0%).
        $decimal = function (mixed $value): mixed {
            if (! is_string($value)) {
                return $value;
            }

            $value = trim(str_replace(',', '', $value));

            return $value === '' ? null : $value;
        };
        $text = fn (mixed $value) => is_string($value) && trim($value) !== '' ? trim($value) : null;

        $items = array_map(fn (mixed $item) => is_array($item) ? [
            ...$item,
            'name' => is_string($item['name'] ?? null) ? trim($item['name']) : ($item['name'] ?? null),
            'description' => $text($item['description'] ?? null),
            'quantity' => $decimal($item['quantity'] ?? null),
            'unit_price' => $decimal($item['unit_price'] ?? null),
            'discount_type' => ($item['discount_type'] ?? null) ?: null,
            'discount_value' => ($item['discount_type'] ?? null) ? $decimal($item['discount_value'] ?? null) : null,
        ] : $item, (array) $this->input('items', []));

        $this->merge([
            'items' => $items,
            'tax_rate' => $decimal($this->input('tax_rate')),
            'global_discount_type' => $this->input('global_discount_type') ?: null,
            'global_discount_value' => $this->input('global_discount_type') ? $decimal($this->input('global_discount_value')) : null,
            'intro' => $text($this->input('intro')),
            'observations' => $text($this->input('observations')),
            'terms' => $text($this->input('terms')),
            'internal_notes' => $text($this->input('internal_notes')),
            'delivery_date' => $this->input('delivery_date') ?: null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'client_id' => ['required', 'integer', Rule::exists('clients', 'id')->whereNull('deleted_at')],
            'currency' => ['required', Rule::enum(Currency::class)],
            'issue_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:2100-12-31'],
            'valid_until' => ['required', 'date_format:Y-m-d', 'after_or_equal:issue_date', 'before_or_equal:2100-12-31'],
            'delivery_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:2100-12-31'],
            'tax_rate' => ['required', 'string', 'regex:/^\d{1,2}(\.\d{1,2})?$/'],
            'global_discount_type' => ['nullable', Rule::in(self::PERCENT_OR_AMOUNT)],
            'global_discount_value' => ['nullable', 'required_with:global_discount_type', 'string', 'regex:'.ServiceRequest::AMOUNT_PATTERN],
            'intro' => ['nullable', 'string', 'max:5000'],
            'observations' => ['nullable', 'string', 'max:5000'],
            'terms' => ['nullable', 'string', 'max:5000'],
            'internal_notes' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.service_id' => ['nullable', 'integer', Rule::exists('services', 'id')],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string', 'max:2000'],
            'items.*.quantity' => ['required', 'string', 'regex:'.self::QUANTITY_PATTERN, 'not_regex:/^0+(\.0+)?$/'],
            'items.*.unit_price' => ['required', 'string', 'regex:'.ServiceRequest::AMOUNT_PATTERN],
            'items.*.discount_type' => ['nullable', Rule::in(self::PERCENT_OR_AMOUNT)],
            'items.*.discount_value' => ['nullable', 'required_with:items.*.discount_type', 'string', 'regex:'.ServiceRequest::AMOUNT_PATTERN],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                try {
                    $this->calculation();
                } catch (QuoteCalculationException $exception) {
                    $validator->errors()->add($exception->field, $exception->getMessage());
                }
            },
        ];
    }

    /**
     * Resultado de QuoteCalculator para los datos validados.
     *
     * @return array<string, mixed>
     */
    public function calculation(): array
    {
        $items = (array) $this->input('items');
        ksort($items); // Mismo orden que SaveQuote: el índice enviado.

        return app(QuoteCalculator::class)->calculate(
            array_map(fn (array $item) => [
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'discount_type' => $item['discount_type'] ?? null,
                'discount_value' => $item['discount_value'] ?? null,
            ], array_values($items)),
            $this->input('global_discount_type'),
            $this->input('global_discount_value'),
            $this->input('tax_rate'),
            $this->input('currency'),
        );
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => 'Agrega al menos un concepto.',
            'items.min' => 'Agrega al menos un concepto.',
            'items.*.name.required' => 'Describe el concepto.',
            'items.*.quantity.regex' => 'Cantidad inválida (máximo 2 decimales).',
            'items.*.quantity.not_regex' => 'La cantidad debe ser mayor que cero.',
            'items.*.unit_price.regex' => 'Precio inválido (máximo 2 decimales).',
            'items.*.unit_price.required' => 'Indica el precio.',
            'items.*.discount_value.regex' => 'Descuento inválido.',
            'valid_until.after_or_equal' => 'La validez no puede ser anterior a la fecha de emisión.',
            'tax_rate.regex' => 'IGV inválido (por ejemplo 18 o 0).',
            'client_id.required' => 'Selecciona el cliente.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'client_id' => 'cliente',
            'currency' => 'moneda',
            'issue_date' => 'fecha de emisión',
            'valid_until' => 'válida hasta',
            'delivery_date' => 'fecha de entrega',
            'tax_rate' => 'IGV',
            'global_discount_value' => 'descuento global',
            'observations' => 'observaciones',
            'terms' => 'condiciones',
            'internal_notes' => 'notas internas',
        ];
    }
}
