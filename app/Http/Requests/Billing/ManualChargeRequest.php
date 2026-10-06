<?php

namespace App\Http\Requests\Billing;

use App\Domain\Shared\Money\Currency;
use App\Http\Requests\Catalog\ServiceRequest;
use App\Models\Charge;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ManualChargeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Charge::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'description' => is_string($this->input('description')) ? trim($this->input('description')) : $this->input('description'),
            'amount' => is_string($this->input('amount')) ? str_replace(',', '', trim($this->input('amount'))) : $this->input('amount'),
            'notes' => is_string($this->input('notes')) && trim($this->input('notes')) !== '' ? trim($this->input('notes')) : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'client_id' => ['required', 'integer', Rule::exists('clients', 'id')->whereNull('deleted_at')],
            'description' => ['required', 'string', 'max:255'],
            'currency' => ['required', Rule::enum(Currency::class)],
            'amount' => ['required', 'string', 'regex:'.ServiceRequest::AMOUNT_PATTERN, 'not_regex:/^0+(\.0+)?$/'],
            'due_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:2100-12-31'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.regex' => 'Ingresa un importe válido, por ejemplo 350 o 350.50 (máximo 2 decimales).',
            'amount.not_regex' => 'El importe debe ser mayor que cero.',
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
            'description' => 'concepto',
            'currency' => 'moneda',
            'amount' => 'importe',
            'due_date' => 'fecha de vencimiento',
        ];
    }
}
