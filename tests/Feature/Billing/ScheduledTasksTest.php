<?php

namespace Tests\Feature\Billing;

use App\Domain\Contracts\Enums\ContractStatus;
use App\Models\Charge;
use App\Models\ClientService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ScheduledTasksTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_generate_charges_command_is_idempotent(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-04 15:00:00', 'UTC'));
        ClientService::factory()->count(3)->create(['next_charge_date' => '2026-10-20']);
        ClientService::factory()->create(['next_charge_date' => '2027-06-01']); // Fuera del horizonte.
        ClientService::factory()->cancelled()->create();

        $this->artisan('billing:generate-charges')->expectsOutputToContain('Cobros generados: 3.')->assertSuccessful();
        $this->artisan('billing:generate-charges')->expectsOutputToContain('Cobros generados: 0.')->assertSuccessful();

        $this->assertSame(3, Charge::count());
    }

    public function test_finished_contracts_are_completed_but_their_charges_remain(): void
    {
        Carbon::setTestNow(Carbon::parse('2031-10-10 15:00:00', 'UTC'));
        $finished = ClientService::factory()->create(['term_months' => 60, 'end_date' => '2031-10-09', 'next_charge_date' => null]);
        $running = ClientService::factory()->create(['term_months' => 60, 'end_date' => '2031-10-10', 'next_charge_date' => null]);
        $open = Charge::factory()->create(['client_id' => $finished->client_id, 'client_service_id' => $finished->id, 'cycle_number' => 5]);

        $this->artisan('contracts:complete-finished')->expectsOutputToContain('Contratos finalizados: 1.')->assertSuccessful();

        $this->assertSame(ContractStatus::Completed, $finished->fresh()->status);
        $this->assertSame(ContractStatus::Active, $running->fresh()->status);
        $this->assertSame('pending', $open->fresh()->status->value);
    }

    public function test_tasks_are_scheduled(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('billing:generate-charges')
            ->expectsOutputToContain('reminders:dispatch')
            ->expectsOutputToContain('contracts:complete-finished')
            ->assertSuccessful();
    }
}
