<?php

namespace App\Domain\Catalog\Actions;

use App\Domain\Shared\Audit\ActivityLogger;
use App\Models\Service;

class SetServiceStatus
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function handle(Service $service, bool $active): void
    {
        if ($service->is_active === $active) {
            return;
        }

        $service->update(['is_active' => $active]);
        $this->logger->log($active ? 'service.activated' : 'service.deactivated', $service);
    }
}
