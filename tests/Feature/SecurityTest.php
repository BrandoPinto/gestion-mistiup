<?php

namespace Tests\Feature;

use App\Models\Charge;
use App\Models\User;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    /** Todas las rutas del panel (cualquier verbo) exigen sesión: un visitante es enviado al login. */
    public function test_every_admin_route_requires_authentication(): void
    {
        $checked = 0;

        foreach (Route::getRoutes() as $route) {
            /** @var RoutingRoute $route */
            if (! in_array('auth', $route->gatherMiddleware(), true)) {
                continue;
            }

            $uri = preg_replace('/\{[^}]+\}/', '1', $route->uri());
            $method = collect($route->methods())->reject(fn (string $verb) => $verb === 'HEAD')->first();

            $this->call($method, '/'.ltrim($uri, '/'))->assertRedirect(route('login'));
            $checked++;
        }

        $this->assertGreaterThan(50, $checked, 'Se esperaban todas las rutas del panel.');
    }

    public function test_only_login_and_public_quote_routes_are_reachable_without_session(): void
    {
        $public = collect(Route::getRoutes()->getRoutes())
            ->reject(fn (RoutingRoute $route) => in_array('auth', $route->gatherMiddleware(), true))
            ->map(fn (RoutingRoute $route) => $route->getName() ?? $route->uri())
            ->reject(fn (string $name) => Str::startsWith($name, ['storage.', 'ignition.', 'boost.', '_']))
            ->sort()
            ->values()
            ->all();

        $this->assertSame(['branding.logo', 'login', 'login.store', 'quotes.public', 'quotes.public.pdf', 'up'], $public);
    }

    public function test_deactivated_user_with_open_session_is_logged_out(): void
    {
        $user = User::factory()->inactive()->create();

        $this->actingAs($user)->get(route('clients.index'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_guests_only_receive_public_route_names(): void
    {
        $html = $this->get(route('login'))->assertOk()->getContent();

        $this->assertStringContainsString('login.store', $html);
        $this->assertStringNotContainsString('clients.index', $html);
        $this->assertStringNotContainsString('users.index', $html);
    }

    public function test_login_and_logout_force_a_full_page_load_so_route_names_are_refreshed(): void
    {
        $user = User::factory()->create(['password' => 'clave-segura-2026']);
        $inertia = ['X-Inertia' => 'true', 'X-Inertia-Version' => ''];

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'clave-segura-2026'], $inertia)
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', route('dashboard'));

        $this->post(route('logout'), [], $inertia)
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', route('login'));
    }

    public function test_forwarded_ip_is_ignored_unless_proxies_are_configured(): void
    {
        $user = User::factory()->create();
        $spoofed = ['X-Forwarded-For' => '203.0.113.9'];

        $this->actingAs($user)->post(route('logout'), [], $spoofed);
        $this->assertDatabaseMissing('activity_logs', ['action' => 'auth.logout', 'ip_address' => '203.0.113.9']);

        config(['app.trusted_proxies' => '*']);
        $this->actingAs($user)->post(route('logout'), [], $spoofed);
        $this->assertDatabaseHas('activity_logs', ['action' => 'auth.logout', 'ip_address' => '203.0.113.9']);
    }

    public function test_errors_become_a_notice_on_inertia_navigation_in_production(): void
    {
        config(['app.debug' => false]);
        $this->actingAs(User::factory()->create());
        $inertia = ['X-Inertia' => 'true', 'X-Inertia-Version' => '', 'Referer' => route('charges.index')];

        $this->get('/cobros/999999', $inertia)
            ->assertRedirect(route('charges.index'))
            ->assertSessionHas('error');

        // Navegación normal: página de error propia, sin detalles internos.
        $this->get('/cobros/999999')->assertNotFound()->assertSee('Página no encontrada')->assertDontSee('vendor');
    }

    public function test_security_headers_are_sent(): void
    {
        $this->get(route('login'))
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'same-origin');

        $this->get('/q/'.str_repeat('x', 48))->assertHeader('Referrer-Policy', 'no-referrer');
    }

    public function test_server_controlled_fields_cannot_be_mass_assigned(): void
    {
        $this->expectException(MassAssignmentException::class);

        (new Charge)->fill(['amount_paid' => '999.00', 'status' => 'paid']);
    }
}
