<?php

namespace Tests\Feature\Settings;

use App\Domain\Shared\Settings\AppSettings;
use App\Models\Charge;
use App\Models\ClientService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BillingSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_lead_days_fall_back_to_config_and_are_validated(): void
    {
        $this->actingAs(User::factory()->create());
        $this->assertSame(45, app(AppSettings::class)->chargeLeadDays());

        $this->put(route('settings.billing.update'), ['charge_lead_days' => 0])->assertSessionHasErrors('charge_lead_days');
        $this->put(route('settings.billing.update'), ['charge_lead_days' => 181])->assertSessionHasErrors('charge_lead_days');
        $this->put(route('settings.billing.update'), ['charge_lead_days' => 10])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('activity_logs', ['action' => 'settings.billing_updated']);
    }

    public function test_generation_uses_the_configured_lead_days(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-04 15:00:00', 'UTC'));
        app(AppSettings::class)->set(AppSettings::CHARGE_LEAD_DAYS, 10);

        ClientService::factory()->create(['next_charge_date' => '2026-10-14']); // Justo en el horizonte.
        ClientService::factory()->create(['next_charge_date' => '2026-10-20']); // Fuera con 10 días (dentro con 45).

        $this->artisan('billing:generate-charges')->expectsOutputToContain('Cobros generados: 1.')->assertSuccessful();
        $this->assertSame(1, Charge::count());
    }
}
