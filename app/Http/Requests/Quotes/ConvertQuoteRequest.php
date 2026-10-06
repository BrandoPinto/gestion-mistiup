<?php

namespace App\Http\Requests\Quotes;

use App\Domain\Billing\Enums\BillingType;
use App\Domain\Billing\Enums\IntervalUnit;
use App\Domain\Billing\Recurrence;
use App\Domain\Contracts\ContractTerms;
use App\Http\Requests\Catalog\ServiceRequest;
use App\Http\Requests\Contracts\ScheduleValidation;
use App\Models\Quote;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Conversión de conceptos de una cotización en servicios contratados. El formulario envía SOLO los
 * conceptos seleccionados; cada uno con su modalidad, precio y fechas (no se asume que todo es recurrente).
 */
class ConvertQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $quote = $this->route('quote');

        return $quote instanceof Quote && $this->user()->can('update', $quote);
    }

    protected function prepareForValidation(): void
    {
        $items = array_map(function (mixed $item) {
            if (! is_array($item)) {
                return $item;
            }

            $oneTime = ($item['billing_type'] ?? null) === BillingType::OneTime->value;
            $hasTerm = ! $oneTime && filter_var($item['has_term'] ?? false, FILTER_VALIDATE_BOOLEAN);

            return [
                ...$item,
                'name' => is_string($item['name'] ?? null) ? trim($item['name']) : null,
                'price' => is_string($item['price'] ?? null) ? str_replace(',', '', trim($item['price'])) : null,
                'interval_unit' => $oneTime ? null : ($item['interval_unit'] ?? null),
                'interval_count' => $oneTime ? null : ($item['interval_count'] ?? null),
                'has_term' => $hasTerm,
                'term_unit' => $hasTerm ? ($item['term_unit'] ?? null) : null,
                'term_count' => $hasTerm ? ($item['term_count'] ?? null) : null,
            ];
        }, array_values((array) $this->input('items', [])));

        $this->merge(['items' => $items]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $quote = $this->route('quote');

        return [
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.quote_item_id' => ['required', 'integer', 'distinct', Rule::exists('quote_items', 'id')->where('quote_id', $quote->id)],
            'items.*.name' => ['required', 'string', 'max:150'],
            'items.*.price' => ['required', 'string', 'regex:'.ServiceRequest::AMOUNT_PATTERN, 'not_regex:/^0+(\.0+)?$/'],
            'items.*.billing_type' => ['required', Rule::enum(BillingType::class)],
            'items.*.interval_unit' => ['nullable', 'required_if:items.*.billing_type,recurring', Rule::enum(IntervalUnit::class)],
            'items.*.interval_count' => ['nullable', 'required_if:items.*.billing_type,recurring', 'integer', 'min:1', 'max:120'],
            'items.*.start_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:2100-12-31'],
            'items.*.has_term' => ['boolean'],
            'items.*.term_unit' => ['nullable', 'required_if:items.*.has_term,true', Rule::enum(IntervalUnit::class)],
            'items.*.term_count' => ['nullable', 'required_if:items.*.has_term,true', 'integer', 'min:1', 'max:240'],
            'items.*.first_charge_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:2100-12-31'],
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

                foreach ($this->input('items') as $index => $item) {
                    ScheduleValidation::check($item, $validator, "items.{$index}.");
                }
            },
        ];
    }

    /**
     * Términos de cada contrato a crear. El primer cobro es siempre el ciclo 1 (contratos nuevos).
     *
     * @return list<array{quote_item_id: int, terms: ContractTerms}>
     */
    public function conversions(): array
    {
        /** @var Quote $quote */
        $quote = $this->route('quote');
        $items = $quote->items()->get()->keyBy('id');

        return array_map(function (array $item) use ($quote, $items) {
            $quoteItem = $items->get((int) $item['quote_item_id']);
            $recurring = $item['billing_type'] === BillingType::Recurring->value;

            return [
                'quote_item_id' => $quoteItem->id,
                'terms' => new ContractTerms(
                    clientId: $quote->client_id,
                    serviceId: $quoteItem->service_id,
                    name: $item['name'],
                    description: $quoteItem->description,
                    currency: $quote->currency,
                    price: $item['price'],
                    recurrence: $recurring ? Recurrence::of(IntervalUnit::from($item['interval_unit']), (int) $item['interval_count']) : null,
                    startDate: CarbonImmutable::parse($item['start_date']),
                    termMonths: $recurring && $item['has_term'] ? ScheduleValidation::termMonths($item) : null,
                    firstCycle: 1,
                    firstChargeDate: CarbonImmutable::parse($item['first_charge_date']),
                    notes: "Contratado desde la cotización {$quote->number}.",
                    quoteItemId: $quoteItem->id,
                ),
            ];
        }, array_values($this->validated('items')));
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => 'Selecciona al menos un concepto para convertir.',
            'items.*.price.regex' => 'Precio inválido (máximo 2 decimales).',
            'items.*.price.not_regex' => 'El precio debe ser mayor que cero.',
            'items.*.interval_unit.required_if' => 'Elige la frecuencia.',
            'items.*.interval_count.required_if' => 'Indica cada cuánto se cobra.',
            'items.*.term_count.required_if' => 'Indica la duración.',
            'items.*.quote_item_id.distinct' => 'Un concepto aparece dos veces.',
        ];
    }
}
