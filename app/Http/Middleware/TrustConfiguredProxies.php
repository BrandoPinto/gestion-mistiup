<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies;

/**
 * Proxies de confianza leídos de config('app.trusted_proxies') en cada petición (compatible con config:cache).
 *
 * Por defecto ninguno: confiar en cualquiera permitiría falsificar la IP del cliente y saltarse los límites
 * de intentos. Solo se activa si el hosting está detrás de un proxy (p. ej. Cloudflare).
 */
class TrustConfiguredProxies extends TrustProxies
{
    protected function proxies()
    {
        $proxies = config('app.trusted_proxies');

        if (! is_string($proxies) || trim($proxies) === '') {
            return null;
        }

        return trim($proxies) === '*' ? '*' : array_map('trim', explode(',', $proxies));
    }
}
