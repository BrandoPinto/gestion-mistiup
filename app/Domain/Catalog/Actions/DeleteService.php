<?php

namespace App\Domain\Catalog\Actions;

use App\Domain\Shared\Audit\ActivityLogger;
use App\Models\Service;
use Illuminate\Support\Facades\DB;

/**
 * Eliminación lógica. Los contratos y cotizaciones guardan su propia copia de nombre y precio,
 * así que eliminar un servicio del catálogo no altera nada ya contratado o cotizado.
 */
class DeleteService
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function handle(Service $service): void
    {
        DB::transaction(function () use ($service) {
            $service->delete();
            $this->logger->log('service.deleted', $service, ['name' => $service->name]);
        });
    }
}
