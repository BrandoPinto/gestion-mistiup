<?php

namespace App\Http\Resources;

use App\Models\ActivityLog;

final class ActivityPresenter
{
    private const LABELS = [
        'client.created' => 'Cliente registrado',
        'client.updated' => 'Datos actualizados',
        'client.deleted' => 'Cliente eliminado',
        'service.created' => 'Servicio agregado al catálogo',
        'service.updated' => 'Servicio actualizado',
        'service.activated' => 'Servicio activado',
        'service.deactivated' => 'Servicio desactivado',
        'service.deleted' => 'Servicio eliminado',
        'contract.created' => 'Servicio contratado',
        'contract.updated' => 'Condiciones actualizadas',
        'contract.cancelled' => 'Contrato cancelado',
        'contract.charges_generated' => 'Cobros generados',
        'contract.completed' => 'Contrato finalizado',
        'charge.created' => 'Cobro registrado',
        'charge.amount_adjusted' => 'Importe ajustado',
        'charge.cancelled' => 'Cobro cancelado',
        'payment.registered' => 'Pago registrado',
        'payment.voided' => 'Pago anulado',
        'quote.created' => 'Cotización creada',
        'quote.updated' => 'Cotización editada',
        'quote.sent' => 'Marcada como enviada',
        'quote.back_to_draft' => 'Volvió a borrador',
        'quote.cancelled' => 'Cotización cancelada',
        'quote.link_enabled' => 'Enlace público activado',
        'quote.link_disabled' => 'Enlace público desactivado',
        'quote.link_regenerated' => 'Enlace público regenerado',
        'quote.converted' => 'Convertida en servicios',
        'user.created' => 'Usuario creado',
        'user.updated' => 'Usuario actualizado',
        'user.password_changed' => 'Cambió su contraseña',
        'user.password_reset' => 'Contraseña restablecida',
        'user.activated' => 'Usuario activado',
        'user.deactivated' => 'Usuario desactivado',
        'settings.payment_method_created' => 'Método de pago agregado',
        'settings.payment_method_updated' => 'Método de pago actualizado',
        'settings.billing_updated' => 'Parámetros de cobro actualizados',
    ];

    /**
     * @return array{id: int, action: string, label: string, detail: ?string, user: ?string, fields: list<string>, created_at: string}
     */
    public static function item(ActivityLog $log): array
    {
        return [
            'id' => $log->id,
            'action' => $log->action,
            'label' => self::LABELS[$log->action] ?? $log->action,
            'detail' => self::detail($log),
            'user' => $log->user?->name,
            'fields' => array_keys($log->properties['changes'] ?? []),
            'created_at' => $log->created_at->toIso8601String(),
        ];
    }

    /** Línea breve con el dato relevante de la acción (importe, motivo, cantidad). */
    private static function detail(ActivityLog $log): ?string
    {
        $properties = $log->properties ?? [];
        $amount = isset($properties['amount'], $properties['currency']) ? "{$properties['currency']} {$properties['amount']}" : null;

        return match ($log->action) {
            'payment.registered' => $amount,
            'payment.voided' => trim(($amount ?? '').(isset($properties['reason']) ? " · {$properties['reason']}" : '')) ?: null,
            'charge.amount_adjusted' => isset($properties['changes']['amount'])
                ? "{$properties['changes']['amount']['from']} → {$properties['changes']['amount']['to']}".(isset($properties['reason']) ? " · {$properties['reason']}" : '')
                : null,
            'contract.charges_generated' => isset($properties['count']) ? "{$properties['count']} cobro(s)" : null,
            'quote.converted' => isset($properties['contracts']) ? count($properties['contracts']).' servicio(s) contratado(s)' : null,
            'charge.cancelled', 'contract.cancelled' => $properties['reason'] ?? null,
            default => null,
        };
    }
}
