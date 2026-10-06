<?php

namespace App\Http\Requests\Settings;

use App\Domain\Shared\Money\Currency;
use App\Rules\ValidRuc;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompanyProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage-settings');
    }

    protected function prepareForValidation(): void
    {
        $text = fn (mixed $value) => is_string($value) && trim($value) !== '' ? trim($value) : null;
        $fields = ['trade_name', 'legal_name', 'ruc', 'address', 'phone', 'email', 'website', 'yape_phone', 'plin_phone', 'wallet_holder', 'quote_intro', 'quote_terms'];

        $accounts = array_values(array_filter(
            array_map(fn (mixed $account) => is_array($account) ? array_map($text, array_intersect_key($account, array_flip(['bank', 'currency', 'number', 'cci', 'holder']))) : null, (array) $this->input('bank_accounts', [])),
            fn (?array $account) => $account !== null && array_filter($account) !== [],
        ));

        $this->merge([
            ...array_combine($fields, array_map(fn (string $field) => $text($this->input($field)), $fields)),
            'email' => ($email = $text($this->input('email'))) === null ? null : strtolower($email),
            'bank_accounts' => $accounts,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'trade_name' => ['nullable', 'string', 'max:150'],
            'legal_name' => ['nullable', 'string', 'max:200'],
            'ruc' => ['nullable', 'string', new ValidRuc],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'bank_accounts' => ['array', 'max:6'],
            'bank_accounts.*.bank' => ['required', 'string', 'max:80'],
            'bank_accounts.*.currency' => ['nullable', Rule::enum(Currency::class)],
            'bank_accounts.*.number' => ['required', 'string', 'max:40'],
            'bank_accounts.*.cci' => ['nullable', 'string', 'max:40'],
            'bank_accounts.*.holder' => ['nullable', 'string', 'max:150'],
            'yape_phone' => ['nullable', 'string', 'max:20'],
            'plin_phone' => ['nullable', 'string', 'max:20'],
            'wallet_holder' => ['nullable', 'string', 'max:150'],
            'quote_intro' => ['nullable', 'string', 'max:5000'],
            'quote_terms' => ['nullable', 'string', 'max:5000'],
            'default_tax_rate' => ['required', 'string', 'regex:/^\d{1,2}(\.\d{1,2})?$/'],
            'default_currency' => ['required', Rule::enum(Currency::class)],
            'quote_validity_days' => ['required', 'integer', 'min:1', 'max:365'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'bank_accounts.*.bank.required' => 'Indica el banco.',
            'bank_accounts.*.number.required' => 'Indica el número de cuenta.',
            'default_tax_rate.regex' => 'IGV inválido (por ejemplo 18).',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'trade_name' => 'nombre comercial',
            'legal_name' => 'razón social',
            'default_tax_rate' => 'IGV',
            'default_currency' => 'moneda',
            'quote_validity_days' => 'validez',
        ];
    }
}
