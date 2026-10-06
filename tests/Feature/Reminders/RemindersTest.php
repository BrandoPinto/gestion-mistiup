<?php

namespace Tests\Feature\Reminders;

use App\Domain\Reminders\Actions\DispatchReminders;
use App\Domain\Reminders\Notifications\BillingReminder;
use App\Models\Charge;
use App\Models\Client;
use App\Models\ClientService;
use App\Models\PaymentMethod;
use App\Models\ReminderRule;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RemindersTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->client = Client::factory()->create(['name' => 'Empresa ABC']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function dispatchOn(string $today): array
    {
        return app(DispatchReminders::class)->handle(CarbonImmutable::parse($today));
    }

    private function charge(string $dueDate, array $overrides = []): Charge
    {
        return Charge::factory()->create(['client_id' => $this->client->id, 'description' => 'Hosting empresarial', 'amount' => '350.00', 'due_date' => $dueDate, ...$overrides]);
    }

    private function notifications()
    {
        return $this->user->notifications()->get();
    }

    public function test_one_month_and_one_week_before_with_example_from_the_requirements(): void
    {
        $this->charge('2026-11-10');

        $this->assertSame(0, $this->dispatchOn('2026-10-09')['charge_due']); // Aún fuera de la ventana de 30 días.
        $this->assertSame(1, $this->dispatchOn('2026-10-11')['charge_due']); // "Vence en un mes" (30 días antes).
        $this->assertSame(0, $this->dispatchOn('2026-10-20')['charge_due']); // Ya avisado, aún no toca el de 7 días.
        $this->assertSame(1, $this->dispatchOn('2026-11-03')['charge_due']); // "Vence en una semana".
        $this->assertSame(0, $this->dispatchOn('2026-11-05')['charge_due']);

        $notifications = $this->notifications();
        $this->assertCount(2, $notifications);
        $this->assertEqualsCanonicalizing(
            ['Vence en 30 días (10/11/2026) · S/ 350.00', 'Vence en 7 días (10/11/2026) · S/ 350.00'],
            $notifications->pluck('data.body')->all(),
        );
        $this->assertSame('Hosting empresarial · Empresa ABC', $notifications->first()->data['title']);
        $this->assertSame('/cobros/'.Charge::sole()->id, $notifications->first()->data['url']);
    }

    public function test_running_the_scheduler_many_times_never_duplicates(): void
    {
        $this->charge('2026-10-20');

        foreach (range(1, 5) as $attempt) {
            $this->dispatchOn('2026-10-15');
        }

        $this->assertCount(1, $this->notifications());
    }

    public function test_a_charge_inside_several_windows_gets_a_single_closest_reminder(): void
    {
        // Cobro manual que ya vence en 5 días: cae en las ventanas de 30 y 7; solo se avisa una vez.
        $this->charge('2026-10-20');

        $this->dispatchOn('2026-10-15');
        $this->dispatchOn('2026-10-16');

        $this->assertCount(1, $this->notifications());
        $this->assertDatabaseHas('reminder_dispatches', ['notified' => false]); // La ventana de 30 quedó cubierta.
    }

    public function test_missed_runs_are_recovered_within_the_window(): void
    {
        $this->charge('2026-11-10');

        // El cron estuvo caído del 11/10 al 14/10: el aviso sale igual el 15/10.
        $this->assertSame(1, $this->dispatchOn('2026-10-15')['charge_due']);
    }

    public function test_paid_and_cancelled_charges_are_not_reminded(): void
    {
        $paid = $this->charge('2026-10-20');
        $this->actingAs($this->user)->post(route('payments.store', $paid), ['amount' => '350', 'paid_on' => now()->toDateString(), 'payment_method_id' => PaymentMethod::value('id')]);
        $this->charge('2026-10-20', ['status' => 'cancelled']);

        $this->dispatchOn('2026-10-15');

        $this->assertCount(0, $this->notifications());
    }

    public function test_rescheduled_due_date_triggers_a_new_reminder(): void
    {
        $charge = $this->charge('2026-10-20');
        $this->dispatchOn('2026-10-15');

        $charge->forceFill(['due_date' => '2026-10-30'])->save();
        $this->dispatchOn('2026-10-25');

        $this->assertCount(2, $this->notifications());
    }

    public function test_overdue_reminder_is_sent_once_the_day_after(): void
    {
        $this->charge('2026-10-10');

        $this->assertSame(0, $this->dispatchOn('2026-10-10')['charge_overdue']); // Vence hoy: aún no está vencido.
        $this->assertSame(1, $this->dispatchOn('2026-10-11')['charge_overdue']);
        $this->assertSame(0, $this->dispatchOn('2026-10-12')['charge_overdue']);

        $overdue = $this->notifications()->firstWhere('data.kind', 'charge_overdue');
        $this->assertSame('danger', $overdue->data['tone']);
        $this->assertStringContainsString('Venció ayer (10/10/2026) · saldo S/ 350.00', $overdue->data['body']);
    }

    public function test_contract_end_reminder(): void
    {
        ClientService::factory()->create(['client_id' => $this->client->id, 'term_months' => 60, 'end_date' => '2031-10-09', 'next_charge_date' => null]);

        $this->assertSame(0, $this->dispatchOn('2031-09-08')['contract_end']);
        $this->assertSame(1, $this->dispatchOn('2031-09-10')['contract_end']);
        $this->assertSame(0, $this->dispatchOn('2031-09-11')['contract_end']);
        $this->assertStringContainsString('Revisa si se renueva', $this->notifications()->sole()->data['body']);
    }

    public function test_paused_rules_and_inactive_users_are_respected(): void
    {
        $inactive = User::factory()->inactive()->create();
        ReminderRule::where('days', 30)->update(['is_active' => false]);
        $this->charge('2026-11-10');

        $this->assertSame(0, $this->dispatchOn('2026-10-15')['charge_due']);
        $this->assertSame(1, $this->dispatchOn('2026-11-04')['charge_due']);

        $this->assertCount(1, $this->notifications());
        $this->assertCount(0, $inactive->notifications()->get());
    }

    public function test_reminders_command_runs_with_lima_date(): void
    {
        // 03:00 UTC del 11/10 = 22:00 del 10/10 en Lima: el cobro del 10/10 todavía NO está vencido.
        Carbon::setTestNow(Carbon::parse('2026-10-11 03:00:00', 'UTC'));
        $this->charge('2026-10-10');

        $this->artisan('reminders:dispatch')->assertSuccessful();

        $this->assertNull($this->notifications()->firstWhere('data.kind', 'charge_overdue'));
    }

    public function test_notification_center_endpoints(): void
    {
        $this->charge('2026-10-20');
        $this->dispatchOn('2026-10-15');
        $notification = DatabaseNotification::sole();
        $this->actingAs($this->user);

        $this->getJson(route('notifications.recent'))->assertJsonPath('unread', 1)->assertJsonPath('data.0.title', 'Hosting empresarial · Empresa ABC');

        $this->post(route('notifications.open', $notification->id))->assertRedirect('/cobros/'.Charge::sole()->id);
        $this->assertNotNull($notification->fresh()->read_at);

        $this->charge('2026-10-21', ['description' => 'Dominio']);
        $this->dispatchOn('2026-10-16');
        $this->post(route('notifications.read-all'));
        $this->assertSame(0, $this->user->unreadNotifications()->count());
    }

    public function test_users_cannot_open_notifications_of_others_and_urls_must_be_internal(): void
    {
        $other = User::factory()->create();
        $other->notify(new BillingReminder(['kind' => 'x', 'tone' => 'neutral', 'title' => 't', 'body' => 'b', 'url' => 'https://malicioso.example', 'target_date' => '2026-10-10']));
        $foreign = $other->notifications()->sole();

        $this->actingAs($this->user)->post(route('notifications.open', $foreign->id))->assertNotFound();
        $this->actingAs($other)->post(route('notifications.open', $foreign->id))->assertRedirect(route('notifications.index'));
    }

    public function test_reminder_rules_can_be_managed(): void
    {
        $this->actingAs($this->user);

        $this->post(route('settings.reminders.store'), ['event' => 'charge_due', 'days' => 15])->assertSessionHasNoErrors();
        $this->post(route('settings.reminders.store'), ['event' => 'charge_due', 'days' => 15])->assertSessionHasErrors('days');

        $rule = ReminderRule::where('days', 15)->sole();
        $this->patch(route('settings.reminders.update', $rule), ['is_active' => false]);
        $this->assertFalse($rule->fresh()->is_active);

        $this->delete(route('settings.reminders.destroy', $rule));
        $this->assertModelMissing($rule);
    }
}
