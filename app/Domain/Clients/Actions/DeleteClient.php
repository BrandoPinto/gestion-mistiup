<?php

namespace App\Domain\Clients\Actions;

use App\Domain\Shared\Audit\ActivityLogger;
use App\Models\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Eliminación lógica (soft delete): el cliente deja de aparecer pero su historial se conserva.
 * No se permite si tiene servicios contratados activos: primero hay que cancelarlos.
 */
class DeleteClient
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function handle(Client $client): void
    {
        $activeContracts = $client->contracts()->active()->count();

        if ($activeContracts > 0) {
            throw ValidationException::withMessages([
                'client' => "No se puede eliminar: tiene {$activeContracts} servicio(s) contratado(s) activo(s). Cancélalos primero o marca el cliente como inactivo.",
            ]);
        }

        DB::transaction(function () use ($client) {
            $client->delete();
            $this->logger->log('client.deleted', $client, ['name' => $client->name]);
        });
    }
}
