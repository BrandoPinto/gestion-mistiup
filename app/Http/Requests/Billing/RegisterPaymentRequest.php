<?php

namespace App\Http\Requests\Billing;

use App\Domain\Shared\Dates\BusinessClock;
use App\Http\Requests\Catalog\ServiceRequest;
use App\Models\Charge;
use App\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Formato de los datos del pago. Las reglas de negocio (saldo, moneda, estado del cobro)
 * se verifican en RegisterPayment con el cobro bloqueado.
 */
class RegisterPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Payment::class) && $this->route('charge') instanceof Charge;
    }

    protected function prepareForValidation(): void
    {
        $trim = fn (mixed $value) => is_string($value) && trim($value) !== '' ? trim($value) : null;

        $this->merge([
            'amount' => is_string($this->input('amount')) ? str_replace(',', '', trim($this->input('amount'))) : $this->input('amount'),
            'reference' => $trim($this->input('reference')),
            'notes' => $trim($this->input('notes')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $attachments = config('billing.attachments');
        $today = app(BusinessClock::class)->todayString();

        return [
            'amount' => ['required', 'string', 'regex:'.ServiceRequest::AMOUNT_PATTERN],
            // No se registran pagos con fecha futura.
            'paid_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:'.$today],
            'payment_method_id' => ['required', 'integer', Rule::exists('payment_methods', 'id')->where('is_active', true)],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'receipt' => ['nullable', 'file', 'max:'.$attachments['max_kb'], 'mimes:'.implode(',', $attachments['mimes'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.regex' => 'Ingresa un monto válido, por ejemplo 350 o 350.50 (máximo 2 decimales).',
            'paid_on.before_or_equal' => 'La fecha del pago no puede ser futura.',
            'receipt.mimes' => 'El comprobante debe ser una imagen (JPG, PNG, WEBP) o un PDF.',
            'receipt.max' => 'El comprobante no puede pesar más de 5 MB.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'amount' => 'monto',
            'paid_on' => 'fecha del pago',
            'payment_method_id' => 'método de pago',
            'reference' => 'número de operación',
            'receipt' => 'comprobante',
        ];
    }
}
