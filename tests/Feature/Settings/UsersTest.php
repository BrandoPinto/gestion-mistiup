<?php

namespace Tests\Feature\Settings;

use App\Domain\Users\Actions\ManageUsers;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UsersTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
        $this->actingAs($this->admin);
    }

    public function test_lists_users_marking_the_current_one(): void
    {
        User::factory()->inactive()->create(['name' => 'Zoe']);

        $this->get(route('users.index'))->assertInertia(fn (Assert $page) => $page
            ->component('users/Index')
            ->has('users', 2)
            ->where('users.0.is_current', true)
            ->where('users.1.is_active', false)
            ->missing('users.0.password'));
    }

    public function test_creates_a_user_with_hashed_password_and_audit_without_the_password(): void
    {
        $this->post(route('users.store'), [
            'name' => 'Lucía Quispe',
            'email' => ' Lucia@Mistiup.PE ',
            'role' => 'admin',
            'password' => 'clave-segura-2026',
            'password_confirmation' => 'clave-segura-2026',
        ])->assertSessionHasNoErrors();

        $user = User::where('email', 'lucia@mistiup.pe')->firstOrFail();
        $this->assertNotSame('clave-segura-2026', $user->password);
        $this->assertTrue(Hash::check('clave-segura-2026', $user->password));
        $this->assertTrue($user->is_active);

        $log = ActivityLog::where('action', 'user.created')->firstOrFail();
        $this->assertStringNotContainsString('clave-segura', json_encode($log->properties));
    }

    public function test_password_policy_is_enforced(): void
    {
        $base = ['name' => 'X', 'email' => 'x@mistiup.pe', 'role' => 'admin'];

        $this->post(route('users.store'), [...$base, 'password' => 'corta1', 'password_confirmation' => 'corta1'])->assertSessionHasErrors('password');
        $this->post(route('users.store'), [...$base, 'password' => 'sololetrasaqui', 'password_confirmation' => 'sololetrasaqui'])->assertSessionHasErrors('password');
        $this->post(route('users.store'), [...$base, 'password' => 'clave-segura-2026', 'password_confirmation' => 'otra-cosa-2026'])->assertSessionHasErrors('password');
        $this->post(route('users.store'), [...$base, 'email' => $this->admin->email, 'password' => 'clave-segura-2026', 'password_confirmation' => 'clave-segura-2026'])->assertSessionHasErrors('email');
    }

    public function test_update_does_not_accept_a_password(): void
    {
        $user = User::factory()->create();

        $this->put(route('users.update', $user), ['name' => 'Nuevo', 'email' => $user->email, 'role' => 'admin', 'password' => 'clave-segura-2026'])
            ->assertSessionHasErrors('password');
    }

    public function test_updates_user_data(): void
    {
        $user = User::factory()->create();

        $this->put(route('users.update', $user), ['name' => 'Nombre corregido', 'email' => 'nuevo@mistiup.pe', 'role' => 'admin'])->assertSessionHasNoErrors();

        $this->assertSame('Nombre corregido', $user->fresh()->name);
        $this->assertDatabaseHas('activity_logs', ['action' => 'user.updated', 'subject_id' => $user->id]);
    }

    public function test_reset_password_ends_the_users_sessions(): void
    {
        config(['session.driver' => 'database']);
        $user = User::factory()->create();
        DB::table('sessions')->insert(['id' => 'abc', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
        DB::table('sessions')->insert(['id' => 'def', 'user_id' => $this->admin->id, 'payload' => '', 'last_activity' => time()]);

        $this->post(route('users.password', $user), ['password' => 'otra-clave-2026', 'password_confirmation' => 'otra-clave-2026'])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('otra-clave-2026', $user->fresh()->password));
        $this->assertDatabaseMissing('sessions', ['id' => 'abc']);
        $this->assertDatabaseHas('sessions', ['id' => 'def']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'user.password_reset', 'subject_id' => $user->id]);
    }

    public function test_deactivated_user_cannot_log_in_and_can_be_reactivated(): void
    {
        $user = User::factory()->create(['password' => 'clave-segura-2026']);

        $this->patch(route('users.status', $user), ['is_active' => false])->assertSessionHasNoErrors();
        $this->assertFalse($user->fresh()->is_active);

        auth()->logout();
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'clave-segura-2026']);
        $this->assertGuest();

        $this->actingAs($this->admin)->patch(route('users.status', $user), ['is_active' => true]);
        $this->assertTrue($user->fresh()->is_active);
    }

    public function test_cannot_deactivate_yourself(): void
    {
        $this->patch(route('users.status', $this->admin), ['is_active' => false])->assertSessionHasErrors('user');
        $this->assertTrue($this->admin->fresh()->is_active);
    }

    public function test_the_last_active_admin_cannot_be_deactivated(): void
    {
        $other = User::factory()->create();
        $this->admin->forceFill(['is_active' => false])->save();

        // El actor actúa con su sesión aunque esté marcado inactivo: se prueba la regla del dominio directamente.
        $this->expectException(ValidationException::class);
        app(ManageUsers::class)->setActive($other, false, $this->admin);
    }
}
