<?php

namespace App\Http\Controllers;

use App\Http\Resources\PaginatedData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Centro de notificaciones del usuario actual. Cada usuario solo ve y modifica las suyas.
 */
class NotificationController extends Controller
{
    private const RECENT = 8;

    public function index(Request $request): Response
    {
        $onlyUnread = $request->boolean('unread');

        $notifications = $request->user()
            ->notifications()
            ->when($onlyUnread, fn ($query) => $query->whereNull('read_at'))
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('notifications/Index', [
            'notifications' => PaginatedData::from($notifications, self::present(...)),
            'filters' => ['unread' => $onlyUnread],
        ]);
    }

    /** Lista corta para el desplegable de la campana (JSON). */
    public function recent(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'unread' => $user->unreadNotifications()->count(),
            'data' => $user->notifications()->limit(self::RECENT)->get()->map(self::present(...)),
        ]);
    }

    /** Marca como leída y lleva al cobro/contrato relacionado. */
    public function open(Request $request, string $notification): RedirectResponse
    {
        $item = $this->find($request, $notification);
        $item->markAsRead();

        $url = (string) ($item->data['url'] ?? '');

        // Solo rutas internas: evita redirecciones abiertas a otros dominios.
        return str_starts_with($url, '/') && ! str_starts_with($url, '//')
            ? redirect($url)
            : redirect()->route('notifications.index');
    }

    public function markRead(Request $request, string $notification): RedirectResponse
    {
        $this->find($request, $notification)->markAsRead();

        return back();
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('success', 'Todas las notificaciones quedaron como leídas.');
    }

    private function find(Request $request, string $id): DatabaseNotification
    {
        return $request->user()->notifications()->whereKey($id)->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    public static function present(DatabaseNotification $notification): array
    {
        return [
            'id' => $notification->id,
            'kind' => $notification->data['kind'] ?? 'info',
            'tone' => $notification->data['tone'] ?? 'neutral',
            'title' => $notification->data['title'] ?? '',
            'body' => $notification->data['body'] ?? '',
            'read_at' => $notification->read_at?->toIso8601String(),
            'created_at' => $notification->created_at->toIso8601String(),
        ];
    }
}
