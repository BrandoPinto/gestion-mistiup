<?php

namespace App\Http\Resources;

use App\Models\Client;

/**
 * Formas de un cliente hacia el frontend. Deben coincidir con resources/js/features/clients/types.ts.
 */
final class ClientPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function listItem(Client $client): array
    {
        return [
            'id' => $client->id,
            'type' => $client->type->value,
            'name' => $client->name,
            'trade_name' => $client->trade_name,
            'document' => $client->displayDocument(),
            'phone' => $client->phone,
            'whatsapp' => $client->whatsapp,
            'email' => $client->email,
            'contact_name' => $client->contact_name,
            'status' => $client->status->value,
            'created_at' => $client->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function detail(Client $client): array
    {
        return [
            ...self::listItem($client),
            'document_type' => $client->document_type->value,
            'document_number' => $client->document_number,
            'address' => $client->address,
            'notes' => $client->notes,
            'updated_at' => $client->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Opción para selectores (contratos, cotizaciones) con los datos que muestra un documento.
     *
     * @return array<string, mixed>
     */
    public static function option(Client $client): array
    {
        return [
            'id' => $client->id,
            'name' => $client->name,
            'document' => $client->displayDocument(),
            'address' => $client->address,
            'email' => $client->email,
            'phone' => $client->whatsapp ?? $client->phone,
            'contact_name' => $client->contact_name,
        ];
    }

    /**
     * Valores editables para el formulario (strings vacíos en lugar de null).
     *
     * @return array<string, string>
     */
    public static function formValues(Client $client): array
    {
        return [
            'type' => $client->type->value,
            'name' => $client->name,
            'trade_name' => $client->trade_name ?? '',
            'document_type' => $client->document_type->value,
            'document_number' => $client->document_number ?? '',
            'phone' => $client->phone ?? '',
            'whatsapp' => $client->whatsapp ?? '',
            'email' => $client->email ?? '',
            'address' => $client->address ?? '',
            'contact_name' => $client->contact_name ?? '',
            'notes' => $client->notes ?? '',
            'status' => $client->status->value,
        ];
    }
}
