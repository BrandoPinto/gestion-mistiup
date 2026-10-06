<?php

namespace Tests\Feature\Settings;

use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentMethodsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_adds_a_method_at_the_end_with_a_unique_code(): void
    {
        $this->post(route('settings.payment-methods.store'), ['name' => ' Tarjeta (POS) '])->assertSessionHasNoErrors();
        $this->post(route('settings.payment-methods.store'), ['name' => 'tarjeta (pos)'])->assertSessionHasErrors('name');

        $method = PaymentMethod::where('name', 'Tarjeta (POS)')->firstOrFail();
        $this->assertSame('tarjeta_pos', $method->code);
        $this->assertSame((int) PaymentMethod::max('sort_order'), $method->sort_order);
        $this->assertTrue($method->is_active);
    }

    public function test_renames_and_pauses_but_keeps_at_least_one_active(): void
    {
        $cash = PaymentMethod::where('code', 'cash')->firstOrFail();

        $this->patch(route('settings.payment-methods.update', $cash), ['name' => 'Efectivo (caja)', 'is_active' => false])->assertSessionHasNoErrors();
        $this->assertSame('Efectivo (caja)', $cash->fresh()->name);
        $this->assertFalse($cash->fresh()->is_active);

        PaymentMethod::where('code', '!=', 'other')->update(['is_active' => false]);
        $other = PaymentMethod::where('code', 'other')->firstOrFail();

        $this->patch(route('settings.payment-methods.update', $other), ['name' => $other->name, 'is_active' => false])->assertSessionHasErrors('is_active');
        $this->assertTrue($other->fresh()->is_active);
    }

    public function test_moves_a_method_up_and_down(): void
    {
        $order = fn () => PaymentMethod::orderBy('sort_order')->pluck('code')->all();
        $this->assertSame(['cash', 'transfer', 'yape', 'plin', 'other'], $order());

        $this->post(route('settings.payment-methods.move', PaymentMethod::where('code', 'yape')->first()), ['direction' => 'up']);
        $this->assertSame(['cash', 'yape', 'transfer', 'plin', 'other'], $order());

        // En el borde no hace nada.
        $this->post(route('settings.payment-methods.move', PaymentMethod::where('code', 'other')->first()), ['direction' => 'down']);
        $this->assertSame(['cash', 'yape', 'transfer', 'plin', 'other'], $order());
    }
}
