<?php

namespace Tests\Feature\Billing;

use App\Domain\Billing\Actions\GenerateContractCharges;
use App\Domain\Billing\Enums\ChargeStatus;
use App\Domain\Contracts\Enums\ContractStatus;
use App\Models\ActivityLog;
use App\Models\Attachment;
use App\Models\Charge;
use App\Models\ClientService;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PaymentsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private int $yape;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-04 15:00:00', 'UTC'));
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        $this->yape = PaymentMethod::where('code', 'yape')->value('id');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function pay(Charge $charge, string $amount, array $extra = [])
    {
        return $this->post(route('payments.store', $charge), [
            'amount' => $amount,
            'paid_on' => '2026-10-04',
            'payment_method_id' => $this->yape,
            'reference' => 'OP-123',
            ...$extra,
        ]);
    }

    public function test_three_partial_payments_settle_a_3000_charge(): void
    {
        $charge = Charge::factory()->create(['amount' => '3000.00']);

        $this->pay($charge, '1000')->assertSessionHasNoErrors();
        $charge->refresh();
        $this->assertSame(ChargeStatus::Partial, $charge->status);
        $this->assertSame('1000.00', (string) $charge->amount_paid->getAmount());
        $this->assertSame('2000.00', (string) $charge->balance()->getAmount());

        $this->pay($charge, '1000')->assertSessionHasNoErrors();
        $this->pay($charge, '1000', ['paid_on' => '2026-10-03'])->assertSessionHasNoErrors();

        $charge->refresh();
        $this->assertSame(ChargeStatus::Paid, $charge->status);
        $this->assertSame('3000.00', (string) $charge->amount_paid->getAmount());
        $this->assertTrue($charge->balance()->isZero());
        // Fecha de pago = la del último pago (el más reciente), no la del último registrado.
        $this->assertSame('2026-10-04', $charge->paid_on->toDateString());
        $this->assertSame(3, Payment::count());
    }

    public function test_decimal_payments_are_exact(): void
    {
        $charge = Charge::factory()->create(['amount' => '0.30']);

        $this->pay($charge, '0.10');
        $this->pay($charge, '0.20');

        $this->assertSame(ChargeStatus::Paid, $charge->fresh()->status);
    }

    public function test_cannot_pay_more_than_the_balance(): void
    {
        $charge = Charge::factory()->create(['amount' => '3000.00']);
        $this->pay($charge, '2500');

        $this->pay($charge, '500.01')->assertSessionHasErrors('amount');

        $this->assertSame('2500.00', (string) $charge->fresh()->amount_paid->getAmount());
        $this->assertSame(1, Payment::count());
    }

    public function test_cannot_pay_a_paid_or_cancelled_charge(): void
    {
        $paid = Charge::factory()->create(['amount' => '100.00']);
        $this->pay($paid, '100');
        $this->pay($paid, '1')->assertSessionHasErrors(['amount' => 'El cobro ya está pagado.']);

        $cancelled = Charge::factory()->create(['status' => 'cancelled']);
        $this->pay($cancelled, '1')->assertSessionHasErrors('amount');
    }

    public function test_payment_takes_the_currency_of_the_charge(): void
    {
        $charge = Charge::factory()->usd('500.00')->create();

        $this->pay($charge, '200', ['currency' => 'PEN'])->assertSessionHasNoErrors();

        $this->assertSame('USD', Payment::sole()->currency->value);
    }

    public function test_payment_validations(): void
    {
        $charge = Charge::factory()->create();

        $this->pay($charge, '0')->assertSessionHasErrors('amount');
        $this->pay($charge, '-5')->assertSessionHasErrors('amount');
        $this->pay($charge, '10.005')->assertSessionHasErrors('amount');
        $this->pay($charge, '10', ['paid_on' => '2026-10-05'])->assertSessionHasErrors(['paid_on' => 'La fecha del pago no puede ser futura.']);

        $inactive = PaymentMethod::create(['code' => 'old', 'name' => 'Viejo', 'is_active' => false]);
        $this->pay($charge, '10', ['payment_method_id' => $inactive->id])->assertSessionHasErrors('payment_method_id');

        $this->assertSame(0, Payment::count());
    }

    public function test_voiding_a_payment_keeps_it_in_history_and_reopens_the_charge(): void
    {
        $charge = Charge::factory()->create(['amount' => '100.00']);
        $this->pay($charge, '100');
        $payment = Payment::sole();

        $this->post(route('payments.void', $payment), ['reason' => 'Operación rechazada por el banco'])->assertSessionHasNoErrors();

        $payment->refresh();
        $charge->refresh();
        $this->assertNotNull($payment->voided_at);
        $this->assertSame($this->user->id, $payment->voided_by);
        $this->assertSame(ChargeStatus::Pending, $charge->status);
        $this->assertTrue($charge->amount_paid->isZero());
        $this->assertNull($charge->paid_on);
        $this->assertDatabaseHas(ActivityLog::class, ['action' => 'payment.voided', 'subject_id' => $charge->id]);

        $this->post(route('payments.void', $payment), ['reason' => 'otra vez'])->assertSessionHasErrors('reason');
        $this->post(route('payments.void', Payment::factory()->create(['client_id' => $charge->client_id, 'charge_id' => $charge->id])), [])->assertSessionHasErrors('reason');
    }

    public function test_receipt_is_stored_privately_with_a_random_name_and_served_only_to_users(): void
    {
        Storage::fake('local');
        $charge = Charge::factory()->create();

        $this->pay($charge, '100', ['receipt' => UploadedFile::fake()->image('voucher yape.png')])->assertSessionHasNoErrors();

        $attachment = Attachment::sole();
        $this->assertSame('payment', $attachment->attachable_type);
        $this->assertSame('voucher yape.png', $attachment->original_name);
        $this->assertStringNotContainsString('voucher', $attachment->path);
        Storage::disk('local')->assertExists($attachment->path);

        $this->get(route('attachments.show', $attachment))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');

        auth()->logout();
        $this->get(route('attachments.show', $attachment))->assertRedirect(route('login'));
    }

    public function test_receipt_must_be_an_image_or_pdf(): void
    {
        Storage::fake('local');
        $charge = Charge::factory()->create();

        $this->pay($charge, '100', ['receipt' => UploadedFile::fake()->create('script.php', 10, 'application/x-php')])
            ->assertSessionHasErrors('receipt');

        $this->assertSame(0, Payment::count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_one_time_contract_completes_when_paid_and_reactivates_if_voided(): void
    {
        $contract = ClientService::factory()->oneTime('2500.00')->create(['next_charge_date' => '2026-10-10']);
        app(GenerateContractCharges::class)->handle($contract, Carbon::parse('2026-10-04')->toImmutable());
        $charge = Charge::sole();

        $this->pay($charge, '2500');
        $this->assertSame(ContractStatus::Completed, $contract->fresh()->status);

        $this->post(route('payments.void', Payment::sole()), ['reason' => 'error']);
        $this->assertSame(ContractStatus::Active, $contract->fresh()->status);
    }

    public function test_payments_index_totals_are_separated_by_currency_and_exclude_voided(): void
    {
        $pen = Charge::factory()->create(['amount' => '1000.00']);
        $usd = Charge::factory()->usd('500.00')->create();
        $this->pay($pen, '300');
        $this->pay($pen, '200');
        $this->pay($usd, '150');
        $this->post(route('payments.void', Payment::where('amount', '200.00')->sole()), ['reason' => 'duplicado']);

        $this->get(route('payments.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('payments/Index')
                ->has('payments.data', 2)
                ->where('totals', [
                    ['currency' => 'PEN', 'amount' => '300.00', 'count' => 1],
                    ['currency' => 'USD', 'amount' => '150.00', 'count' => 1],
                ])
                ->etc());
    }
}
