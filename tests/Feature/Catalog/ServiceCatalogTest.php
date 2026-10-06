<?php

namespace Tests\Feature\Catalog;

use App\Domain\Billing\Enums\BillingType;
use App\Domain\Billing\Enums\IntervalUnit;
use App\Models\ActivityLog;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ServiceCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'Hosting empresarial',
            'description' => 'Con correo corporativo',
            'default_currency' => 'PEN',
            'default_price' => '350',
            'default_billing_type' => 'recurring',
            'default_interval_unit' => 'year',
            'default_interval_count' => '1',
            'is_active' => true,
            ...$overrides,
        ];
    }

    public function test_creates_a_service_with_exact_decimal_price(): void
    {
        $this->post(route('services.store'), $this->payload(['default_price' => '1,234.5']))->assertSessionHasNoErrors();

        $service = Service::sole();
        $this->assertSame('1234.50', (string) $service->default_price->getAmount());
        $this->assertSame('PEN', $service->default_price->getCurrency()->getCurrencyCode());
        $this->assertDatabaseHas(ActivityLog::class, ['action' => 'service.created', 'subject_type' => 'service']);
    }

    public function test_twelve_months_is_stored_as_one_year(): void
    {
        $this->post(route('services.store'), $this->payload(['default_interval_unit' => 'month', 'default_interval_count' => '12']));

        $service = Service::sole();
        $this->assertSame(IntervalUnit::Year, $service->default_interval_unit);
        $this->assertSame(1, $service->default_interval_count);
        $this->assertSame('Anual', $service->billingLabel());
    }

    public function test_one_time_service_discards_any_interval(): void
    {
        $this->post(route('services.store'), $this->payload(['default_billing_type' => 'one_time', 'default_interval_unit' => 'month', 'default_interval_count' => '3']));

        $service = Service::sole();
        $this->assertSame(BillingType::OneTime, $service->default_billing_type);
        $this->assertNull($service->default_interval_unit);
        $this->assertNull($service->default_interval_count);
    }

    public function test_recurring_service_requires_a_frequency_up_to_ten_years(): void
    {
        $this->post(route('services.store'), $this->payload(['default_interval_unit' => null, 'default_interval_count' => null]))
            ->assertSessionHasErrors(['default_interval_unit', 'default_interval_count']);

        $this->post(route('services.store'), $this->payload(['default_interval_unit' => 'year', 'default_interval_count' => '11']))
            ->assertSessionHasErrors(['default_interval_count' => 'La frecuencia no puede superar 10 años.']);

        $this->assertDatabaseCount('services', 0);
    }

    public function test_price_is_optional_but_must_be_a_positive_amount_with_two_decimals(): void
    {
        foreach (['-10', '10.005', 'abc', '1e3'] as $invalid) {
            $this->post(route('services.store'), $this->payload(['default_price' => $invalid]))->assertSessionHasErrors('default_price');
        }

        $this->post(route('services.store'), $this->payload(['default_price' => '']))->assertSessionHasNoErrors();
        $this->assertNull(Service::sole()->default_price);
    }

    public function test_only_pen_and_usd_are_accepted(): void
    {
        $this->post(route('services.store'), $this->payload(['default_currency' => 'EUR']))->assertSessionHasErrors('default_currency');
    }

    public function test_updates_currency_and_price_together(): void
    {
        $service = Service::factory()->create(['default_currency' => 'PEN', 'default_price' => '350.00']);

        $this->put(route('services.update', $service), $this->payload(['default_currency' => 'USD', 'default_price' => '99.90']))->assertSessionHasNoErrors();

        $service->refresh();
        $this->assertSame('USD', $service->default_price->getCurrency()->getCurrencyCode());
        $this->assertSame('99.90', (string) $service->default_price->getAmount());
        $this->assertDatabaseHas(ActivityLog::class, ['action' => 'service.updated', 'subject_id' => $service->id]);
    }

    public function test_status_can_be_toggled_and_is_logged(): void
    {
        $service = Service::factory()->create();

        $this->patch(route('services.status', $service), ['is_active' => false])->assertSessionHasNoErrors();

        $this->assertFalse($service->fresh()->is_active);
        $this->assertDatabaseHas(ActivityLog::class, ['action' => 'service.deactivated', 'subject_id' => $service->id]);
    }

    public function test_delete_is_soft(): void
    {
        $service = Service::factory()->create();

        $this->delete(route('services.destroy', $service))->assertSessionHasNoErrors();

        $this->assertSoftDeleted($service);
    }

    public function test_index_lists_active_first_and_filters(): void
    {
        Service::factory()->inactive()->create(['name' => 'A inactivo']);
        Service::factory()->create(['name' => 'Z activo']);
        Service::factory()->recurring('month', 1)->create(['name' => 'Mantenimiento web']);

        $this->get(route('services.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('catalog/Index')
                ->has('services.data', 3)
                ->where('services.data.0.name', 'Mantenimiento web')
                ->where('services.data.0.billing_label', 'Mensual')
                ->where('services.data.2.name', 'A inactivo'));

        $this->get(route('services.index', ['status' => 'inactive']))
            ->assertInertia(fn (Assert $page) => $page->has('services.data', 1));

        $this->get(route('services.index', ['search' => 'manten']))
            ->assertInertia(fn (Assert $page) => $page->has('services.data', 1));
    }

    public function test_price_reaches_the_frontend_as_decimal_string(): void
    {
        Service::factory()->create(['default_price' => '80.00']);

        $this->get(route('services.index'))
            ->assertInertia(fn (Assert $page) => $page->where('services.data.0.default_price', ['amount' => '80.00', 'currency' => 'PEN']));
    }
}
