<?php

namespace App\Domain\Quotes\Actions;

use App\Models\Quote;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\DB;

/**
 * Registra que el cliente abrió el enlace público. Solo fechas y un contador: sin IP ni navegador.
 * No cuenta las visitas de usuarios con sesión iniciada (tú revisando el enlace) y agrupa las recargas
 * de una misma sesión en una sola visita durante 30 minutos.
 */
class RecordQuoteView
{
    private const SESSION_WINDOW_SECONDS = 1800;

    public function handle(Quote $quote, Session $session, bool $isStaff): void
    {
        if ($isStaff) {
            return;
        }

        $key = 'quote_viewed.'.$quote->id;
        $lastSeen = (int) $session->get($key, 0);

        if (now()->timestamp - $lastSeen < self::SESSION_WINDOW_SECONDS) {
            return;
        }

        $session->put($key, now()->timestamp);

        // Actualizaciones atómicas en SQL: dos aperturas simultáneas no pierden visitas.
        Quote::query()->whereKey($quote->id)->update([
            'view_count' => DB::raw('view_count + 1'),
            'last_viewed_at' => now(),
        ]);
        Quote::query()->whereKey($quote->id)->whereNull('first_viewed_at')->update(['first_viewed_at' => now()]);
    }
}
