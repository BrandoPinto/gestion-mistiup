<?php

namespace App\Http\Requests\Clients;

use App\Domain\Clients\Enums\ClientStatus;
use App\Domain\Clients\Enums\ClientType;
use App\Domain\Clients\Enums\DocumentType;
use App\Models\Client;
use App\Rules\ValidRuc;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validación compartida por alta y edición de clientes.
 */
class ClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        $client = $this->route('client');

        return $client instanceof Client
            ? $this->user()->can('update', $client)
            : $this->user()->can('create', Client::class);
    }

    protected function prepareForValidation(): void
    {
        $documentType = (string) $this->input('document_type', DocumentType::None->value);
        $documentNumber = $this->clean($this->input('document_number'));

        $this->merge([
            'name' => $this->clean($this->input('name')),
            'trade_name' => $this->clean($this->input('trade_name')),
            'document_type' => $documentType,
            'document_number' => $documentType === DocumentType::None->value || $documentNumber === null
                ? null
                : strtoupper(preg_replace('/\s+/', '', $documentNumber)),
            'phone' => $this->phoneDigits($this->input('phone')),
            'whatsapp' => $this->phoneDigits($this->input('whatsapp')),
            'email' => ($email = $this->clean($this->input('email'))) === null ? null : strtolower($email),
            'address' => $this->clean($this->input('address')),
            'contact_name' => $this->clean($this->input('contact_name')),
            'notes' => $this->clean($this->input('notes')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $client = $this->route('client');

        return [
            'type' => ['required', Rule::enum(ClientType::class)],
            'name' => ['required', 'string', 'max:255'],
            'trade_name' => ['nullable', 'string', 'max:255'],
            'document_type' => ['required', Rule::enum(DocumentType::class)],
            'document_number' => [
                Rule::requiredIf(fn () => $this->input('document_type') !== DocumentType::None->value),
                'nullable',
                'string',
                ...$this->documentFormatRules(),
                Rule::unique('clients', 'document_number')
                    ->where('document_type', $this->input('document_type'))
                    ->whereNull('deleted_at')
                    ->ignore($client instanceof Client ? $client->id : null),
            ],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^\+?\d{6,15}$/'],
            'whatsapp' => ['nullable', 'string', 'max:20', 'regex:/^\+?\d{9,15}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', Rule::enum(ClientStatus::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'document_number.required' => 'Ingresa el número de documento o elige "Sin documento".',
            'document_number.regex' => $this->input('document_type') === DocumentType::Dni->value
                ? 'El DNI debe tener 8 dígitos.'
                : 'El carné de extranjería debe tener entre 6 y 12 caracteres alfanuméricos.',
            'document_number.unique' => 'Ya existe un cliente registrado con ese documento.',
            'phone.regex' => 'Ingresa un teléfono válido (solo números, opcionalmente con +).',
            'whatsapp.regex' => 'Ingresa un número de WhatsApp válido (mínimo 9 dígitos).',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'type' => 'tipo de cliente',
            'name' => 'nombre o razón social',
            'trade_name' => 'nombre comercial',
            'document_type' => 'tipo de documento',
            'document_number' => 'número de documento',
            'whatsapp' => 'WhatsApp',
            'contact_name' => 'persona de contacto',
            'status' => 'estado',
        ];
    }

    /**
     * @return array<int, mixed>
     */
    private function documentFormatRules(): array
    {
        return match ($this->input('document_type')) {
            DocumentType::Ruc->value => [new ValidRuc],
            DocumentType::Dni->value => ['regex:/^\d{8}$/'],
            DocumentType::Ce->value => ['regex:/^[A-Z0-9]{6,12}$/'],
            default => [],
        };
    }

    private function clean(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /** Conserva solo dígitos y un "+" inicial: "987 654 321" → "987654321". */
    private function phoneDigits(mixed $value): ?string
    {
        $value = $this->clean($value);

        if ($value === null) {
            return null;
        }

        $normalized = (str_starts_with($value, '+') ? '+' : '').preg_replace('/\D/', '', $value);

        return $normalized === '' || $normalized === '+' ? null : $normalized;
    }
}
