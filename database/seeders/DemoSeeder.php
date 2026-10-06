<?php

namespace Database\Seeders;

use App\Domain\Billing\Actions\CreateManualCharge;
use App\Domain\Billing\Actions\GenerateContractCharges;
use App\Domain\Billing\Actions\RegisterPayment;
use App\Domain\Shared\Dates\BusinessClock;
use App\Domain\Shared\Money\Currency;
use App\Models\Client;
use App\Models\ClientService;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Service;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Datos de ejemplo para desarrollo local. Nunca ejecutar en producción.
 *   php artisan db:seed --class=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('DemoSeeder no puede ejecutarse en producción.');
        }

        // Idempotente: cada bloque solo se carga si aún no hay datos de ese tipo.
        if (Client::count() === 0) {
            Client::factory()->count(22)->create();
            Client::factory()->person()->count(6)->create();
            Client::factory()->inactive()->count(4)->create();
        }

        $this->seedCatalog();

        if (ClientService::count() === 0) {
            $this->seedContracts();
        }

        // Idempotente: solo crea los cobros que faltan dentro del horizonte.
        $today = app(BusinessClock::class)->today();
        ClientService::active()->each(fn (ClientService $contract) => app(GenerateContractCharges::class)->handle($contract, $today));

        if (Payment::valid()->count() === 0) {
            $this->seedPaymentHistory($today);
        }
    }

    /**
     * Cobros sueltos ya pagados en los últimos meses, para ver el gráfico de ingresos.
     * Usa las mismas Actions del sistema: los saldos y estados quedan coherentes.
     */
    private function seedPaymentHistory(CarbonImmutable $today): void
    {
        $clients = Client::where('status', 'active')->orderBy('id')->limit(5)->pluck('id');
        $methodId = PaymentMethod::where('code', 'transfer')->value('id');
        $userId = User::query()->value('id');
        $amounts = ['1850.00', '2400.00', '1320.00', '3100.00', '2750.00', '1980.00', '2260.00', '3480.00', '2900.00'];

        foreach ($amounts as $index => $amount) {
            $date = $today->startOfMonth()->subMonths(count($amounts) - $index)->addDays(9);
            $charge = app(CreateManualCharge::class)->handle($clients[$index % $clients->count()], 'Servicios del mes', Currency::PEN, $amount, $date, null, $userId);
            app(RegisterPayment::class)->handle($charge, $amount, $date, $methodId, 'DEMO-'.($index + 1), null, null, $userId);

            if ($index % 3 === 0) {
                $usdCharge = app(CreateManualCharge::class)->handle($clients[0], 'Licencia SaaS', Currency::USD, '199.00', $date, null, $userId);
                app(RegisterPayment::class)->handle($usdCharge, '199.00', $date, $methodId, null, null, null, $userId);
            }
        }
    }

    private function seedContracts(): void
    {
        $clients = Client::where('status', 'active')->orderBy('id')->limit(6)->get();
        $hosting = Service::where('name', 'Hosting empresarial')->first();
        $domain = Service::where('name', 'Dominio .com')->first();
        $maintenance = Service::where('name', 'Mantenimiento web')->first();

        foreach ($clients as $index => $client) {
            // Hosting anual con distintas fechas de inicio, incluido uno iniciado en 2021.
            $start = now()->subYears($index % 2 === 0 ? 5 : 0)->addDays(5 + $index * 9)->toDateString();
            ClientService::factory()->create([
                'client_id' => $client->id,
                'service_id' => $hosting?->id,
                'name' => 'Hosting empresarial',
                'start_date' => $start,
                'next_cycle_number' => $index % 2 === 0 ? 6 : 1,
                'next_charge_date' => now()->addDays(5 + $index * 9)->toDateString(),
            ]);

            if ($index < 3) {
                ClientService::factory()->create([
                    'client_id' => $client->id,
                    'service_id' => $domain?->id,
                    'name' => 'Dominio .com',
                    'price' => '80.00',
                    'start_date' => now()->addDays(20 + $index)->toDateString(),
                    'next_charge_date' => now()->addDays(20 + $index)->toDateString(),
                ]);
            }
        }

        if ($clients->isNotEmpty()) {
            ClientService::factory()->create([
                'client_id' => $clients->first()->id,
                'service_id' => $maintenance?->id,
                'name' => 'Mantenimiento web',
                'price' => '150.00',
                'interval_unit' => 'month',
                'interval_count' => 1,
                'start_date' => now()->startOfMonth()->toDateString(),
                'next_cycle_number' => 2,
                'next_charge_date' => now()->startOfMonth()->addMonth()->toDateString(),
            ]);
            ClientService::factory()->oneTime()->create([
                'client_id' => $clients->last()->id,
                'start_date' => now()->addDays(3)->toDateString(),
                'next_charge_date' => now()->addDays(3)->toDateString(),
            ]);
        }
    }

    private function seedCatalog(): void
    {
        $services = [
            ['Hosting empresarial', 'Alojamiento web con correo corporativo y SSL.', '350.00', 'PEN', 'recurring', 'year', 1],
            ['Dominio .com', 'Registro y renovación del dominio.', '80.00', 'PEN', 'recurring', 'year', 1],
            ['Dominio .pe', 'Registro y renovación del dominio peruano.', '150.00', 'PEN', 'recurring', 'year', 1],
            ['Desarrollo web', 'Diseño y desarrollo de sitio web a medida.', '2500.00', 'PEN', 'one_time', null, null],
            ['Mantenimiento web', 'Actualizaciones, copias de seguridad y soporte.', '150.00', 'PEN', 'recurring', 'month', 1],
            ['Sistema a medida', 'Desarrollo de sistema administrativo.', null, 'PEN', 'one_time', null, null],
            ['Soporte técnico', 'Bolsa de horas de soporte.', '300.00', 'PEN', 'recurring', 'month', 3],
            ['Diseño de logotipo', 'Identidad visual básica.', '450.00', 'PEN', 'one_time', null, null],
            ['Licencia SaaS', 'Licencia anual de software de terceros.', '199.00', 'USD', 'recurring', 'year', 1],
        ];

        foreach ($services as [$name, $description, $price, $currency, $billing, $unit, $count]) {
            Service::firstOrCreate(['name' => $name], [
                'description' => $description,
                'default_currency' => $currency,
                'default_price' => $price,
                'default_billing_type' => $billing,
                'default_interval_unit' => $unit,
                'default_interval_count' => $count,
            ]);
        }
    }
}
