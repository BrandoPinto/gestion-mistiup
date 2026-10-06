<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_updates_own_data(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('account.update'), ['name' => 'Ana', 'email' => 'ANA@mistiup.pe'])->assertSessionHasNoErrors();

        $this->assertSame('ana@mistiup.pe', $user->fresh()->email);
    }

    public function test_changing_password_requires_the_current_one(): void
    {
        $user = User::factory()->create(['password' => 'clave-actual-2026']);
        $this->actingAs($user);

        $this->put(route('account.password'), ['current_password' => 'equivocada', 'password' => 'clave-nueva-2026', 'password_confirmation' => 'clave-nueva-2026'])
            ->assertSessionHasErrors('current_password');
        $this->put(route('account.password'), ['current_password' => 'clave-actual-2026', 'password' => 'clave-actual-2026', 'password_confirmation' => 'clave-actual-2026'])
            ->assertSessionHasErrors('password');

        $this->put(route('account.password'), ['current_password' => 'clave-actual-2026', 'password' => 'clave-nueva-2026', 'password_confirmation' => 'clave-nueva-2026'])
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('clave-nueva-2026', $user->fresh()->password));
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('activity_logs', ['action' => 'user.password_changed', 'subject_id' => $user->id]);
    }
}
