<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class NavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_renders_with_shared_props(): void
    {
        $user = User::factory()->create(['name' => 'Ana Pérez']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard/Index')
                ->where('auth.user.name', 'Ana Pérez')
                ->where('app.timezone', 'America/Lima')
                ->missing('auth.user.password'));
    }

    public function test_every_sidebar_section_is_reachable(): void
    {
        $this->actingAs(User::factory()->create());

        foreach (['contracts.index', 'charges.index', 'payments.index', 'due.index', 'notifications.index', 'settings.index', 'settings.reminders', 'quotes.index', 'users.index', 'account.edit', 'settings.payment-methods', 'settings.billing'] as $name) {
            $this->get(route($name))->assertOk();
        }
    }
}
