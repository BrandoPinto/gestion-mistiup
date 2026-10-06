<?php

namespace App\Http\Resources;

use App\Models\CompanyProfile;

/**
 * Datos de la empresa emisora tal como aparecen en un documento. Deben coincidir con features/quotes/types.ts (CompanyBlock).
 */
final class CompanyPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function forDocument(CompanyProfile $company): array
    {
        return [
            'name' => $company->displayName(),
            'legal_name' => $company->legal_name,
            'ruc' => $company->ruc,
            'address' => $company->address,
            'phone' => $company->phone,
            'email' => $company->email,
            'website' => $company->website,
            'logo_url' => $company->logoUrl(),
            'bank_accounts' => array_values($company->bank_accounts ?? []),
            'yape_phone' => $company->yape_phone,
            'plin_phone' => $company->plin_phone,
            'wallet_holder' => $company->wallet_holder,
        ];
    }

    /**
     * Valores por defecto para una cotización nueva.
     *
     * @return array{currency: string, tax_rate: string, validity_days: int, intro: ?string, terms: ?string}
     */
    public static function quoteDefaults(CompanyProfile $company): array
    {
        return [
            'currency' => $company->default_currency->value,
            'tax_rate' => (string) $company->default_tax_rate,
            'validity_days' => $company->quote_validity_days,
            'intro' => $company->quote_intro,
            'terms' => $company->quote_terms,
        ];
    }
}
