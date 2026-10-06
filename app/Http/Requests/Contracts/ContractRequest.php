<?php

namespace App\Http\Requests\Contracts;

use App\Domain\Billing\Enums\BillingType;
use App\Domain\Billing\Enums\IntervalUnit;
use App\Domain\Billing\Recurrence;
use App\Domain\Contracts\ContractTerms;
use App\Domain\Shared\Money\Currency;
use App\Http\Requests\Catalog\ServiceRequest;
use App\Models\ClientService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Alta y edición de un servicio contratado. Además de los campos, valida la coherencia del calendario:
 * frecuencia normalizable, duración = número exacto de ciclos y primer ciclo dentro del contrato.
 */
class ContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        $contract = $this->route('contract');

        return $contract instanceof ClientService
            ? $this->user()->can('update', $contract)
            : $this->user()->can('create', ClientService::class);
    }

    protected function prepareForValidation(): void
    {
        $oneTime = $this->input('billing_type') === BillingType::OneTime->value;
        $hasTerm = ! $oneTime && $this->boolean('has_term');
        $trim = fn (mixed $value) => is_string($value) && trim($value) !== '' ? trim($value) : null;

        $this->merge([
            'name' => $trim($this->input('name')),
            'description' => $trim($this->input('description')),
            'notes' => $trim($this->input('notes')),
            'price' => is_string($this->input('price')) ? str_replace(',', '', trim($this->input('price'))) : $this->input('price'),
            'interval_unit' => $oneTime ? null : $this->input('interval_unit'),
            'interval_count' => $oneTime ? null : $this->input('interval_count'),
            'has_term' => $hasTerm,
            'term_unit' => $hasTerm ? $this->input('term_unit') : null,
            'term_count' => $hasTerm ? $this->input('term_count') : null,
            'first_cycle' => $oneTime ? 1 : $this->input('first_cycle'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $recurring = Rule::requiredIf(fn () => $this->input('billing_type') === BillingType::Recurring->value);
        $withTerm = Rule::requiredIf(fn () => $this->boolean('has_term'));

        return [
            'client_id' => ['required', 'integer', Rule::exists('clients', 'id')->whereNull('deleted_at')],
            'service_id' => ['nullable', 'integer', Rule::exists('services', 'id')->whereNull('deleted_at')],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'currency' => ['required', Rule::enum(Currency::class)],
            'price' => ['required', 'string', 'regex:'.ServiceRequest::AMOUNT_PATTERN],
            'billing_type' => ['required', Rule::enum(BillingType::class)],
            'interval_unit' => [$recurring, 'nullable', Rule::enum(IntervalUnit::class)],
            'interval_count' => [$recurring, 'nullable', 'integer', 'min:1', 'max:120'],
            'start_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:2100-12-31'],
            'has_term' => ['boolean'],
            'term_unit' => [$withTerm, 'nullable', Rule::enum(IntervalUnit::class)],
            'term_count' => [$withTerm, 'nullable', 'integer', 'min:1', 'max:240'],
            // Con cobros generados el calendario queda fijo y estos dos campos no se usan.
            'first_cycle' => [Rule::requiredIf(! $this->scheduleLocked()), 'nullable', 'integer', 'min:1', 'max:100000'],
            'first_charge_date' => [Rule::requiredIf(! $this->scheduleLocked()), 'nullable', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:2100-12-31'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty() || $this->scheduleLocked()) {
                    return;
                }

                $totalCycles = ScheduleValidation::check($this->all(), $validator);

                if ($totalCycles !== null && (int) $this->input('first_cycle') > $totalCycles) {
                    $validator->errors()->add('first_cycle', 'El primer cobro debe estar dentro de la duración del contrato.');
                }
            },
        ];
    }

    /**
     * Términos validados del contrato, listos para las Actions.
     */
    public function terms(): ContractTerms
    {
        $data = $this->validated();
        $locked = $this->route('contract');

        if ($this->scheduleLocked() && $locked instanceof ClientService) {
            // Calendario fijo: se conservan los valores guardados; SaveContract tampoco los modifica.
            return new ContractTerms(
                clientId: $locked->client_id,
                serviceId: isset($data['service_id']) ? (int) $data['service_id'] : null,
                name: $data['name'],
                description: $data['description'],
                currency: $locked->currency,
                price: $data['price'],
                recurrence: $locked->recurrence(),
                startDate: $locked->start_date,
                termMonths: $locked->term_months,
                firstCycle: $locked->next_cycle_number,
                firstChargeDate: $locked->next_charge_date ?? $locked->start_date,
                notes: $data['notes'],
            );
        }

        $recurring = $data['billing_type'] === BillingType::Recurring->value;

        return new ContractTerms(
            clientId: (int) $data['client_id'],
            serviceId: isset($data['service_id']) ? (int) $data['service_id'] : null,
            name: $data['name'],
            description: $data['description'],
            currency: Currency::from($data['currency']),
            price: $data['price'],
            recurrence: $recurring ? Recurrence::of(IntervalUnit::from($data['interval_unit']), (int) $data['interval_count']) : null,
            startDate: CarbonImmutable::parse($data['start_date']),
            termMonths: $recurring && $data['has_term'] ? ScheduleValidation::termMonths($data) : null,
            firstCycle: (int) $data['first_cycle'],
            firstChargeDate: CarbonImmutable::parse($data['first_charge_date']),
            notes: $data['notes'],
        );
    }

    private ?bool $scheduleLocked = null;

    public function scheduleLocked(): bool
    {
        $contract = $this->route('contract');

        return $this->scheduleLocked ??= $contract instanceof ClientService && $contract->scheduleLocked();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'price.regex' => 'Ingresa un importe válido, por ejemplo 350 o 350.50 (máximo 2 decimales).',
            'interval_unit.required' => 'Elige la frecuencia del cobro.',
            'interval_count.required' => 'Indica cada cuánto se cobra.',
            'term_count.required' => 'Indica la duración del contrato.',
            'client_id.required' => 'Selecciona el cliente.',
            'client_id.exists' => 'El cliente seleccionado no existe.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'client_id' => 'cliente',
            'service_id' => 'servicio del catálogo',
            'name' => 'nombre del servicio',
            'currency' => 'moneda',
            'price' => 'precio',
            'start_date' => 'fecha de inicio',
            'term_count' => 'duración',
            'first_cycle' => 'primer cobro',
            'first_charge_date' => 'fecha del primer cobro',
        ];
    }
}
