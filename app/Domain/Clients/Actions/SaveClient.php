<?php

namespace App\Domain\Clients\Actions;

use App\Domain\Shared\Audit\ActivityLogger;
use App\Domain\Shared\Audit\ModelChanges;
use App\Models\Client;
use Illuminate\Support\Facades\DB;

/**
 * Crea o actualiza un cliente y deja constancia en el log de actividad (con los cambios campo a campo).
 */
class SaveClient
{
    public function __construct(private readonly ActivityLogger $logger) {}

    /**
     * @param  array<string, mixed>  $data  Datos ya validados por ClientRequest.
     */
    public function handle(array $data, ?Client $client = null): Client
    {
        return DB::transaction(function () use ($data, $client) {
            if ($client === null) {
                $client = Client::create($data);
                $this->logger->log('client.created', $client);

                return $client;
            }

            $client->fill($data);
            $changes = ModelChanges::pending($client);
            $client->save();

            if ($changes !== []) {
                $this->logger->log('client.updated', $client, ['changes' => $changes]);
            }

            return $client;
        });
    }
}
