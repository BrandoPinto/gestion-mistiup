<?php

namespace Tests\Feature\Settings;

use App\Models\Charge;
use App\Models\Client;
use App\Models\CompanyProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CompanyProfileTest extends TestCase
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
            'trade_name' => 'Mistiup',
            'legal_name' => 'Mistiup S.A.C.',
            'ruc' => '20131312955',
            'email' => 'Hola@Mistiup.pe',
            'bank_accounts' => [
                ['bank' => 'BCP', 'currency' => 'PEN', 'number' => '215-1234567-0-12', 'cci' => '00221500123456701234', 'holder' => 'Mistiup S.A.C.'],
                ['bank' => '', 'currency' => '', 'number' => '', 'cci' => '', 'holder' => ''], // Fila vacía: se descarta.
            ],
            'default_tax_rate' => '18',
            'default_currency' => 'PEN',
            'quote_validity_days' => '15',
            'quote_terms' => 'Precios incluyen IGV.',
            ...$overrides,
        ];
    }

    public function test_updates_company_profile(): void
    {
        $this->put(route('settings.company.update'), $this->payload())->assertSessionHasNoErrors();

        $company = CompanyProfile::current();
        $this->assertSame('Mistiup', $company->displayName());
        $this->assertSame('hola@mistiup.pe', $company->email);
        $this->assertCount(1, $company->bank_accounts);
        $this->assertSame('BCP', $company->bank_accounts[0]['bank']);
    }

    public function test_validates_ruc_and_bank_rows(): void
    {
        $this->put(route('settings.company.update'), $this->payload(['ruc' => '20131312956']))->assertSessionHasErrors('ruc');
        $this->put(route('settings.company.update'), $this->payload(['bank_accounts' => [['bank' => 'BCP', 'number' => '']]]))->assertSessionHasErrors('bank_accounts.0.number');
    }

    public function test_logo_upload_is_private_but_served_publicly_and_rejects_svg(): void
    {
        Storage::fake('local');

        $this->post(route('settings.company.logo'), ['logo' => UploadedFile::fake()->createWithContent('logo.svg', '<svg><script>alert(1)</script></svg>')])
            ->assertSessionHasErrors('logo');

        $this->post(route('settings.company.logo'), ['logo' => UploadedFile::fake()->image('logo.png', 400, 120)])->assertSessionHasNoErrors();

        $company = CompanyProfile::current();
        Storage::disk('local')->assertExists($company->logo_path);

        auth()->logout();
        $this->get($company->logoUrl())->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_charges_use_the_company_tax_rate(): void
    {
        $this->put(route('settings.company.update'), $this->payload(['default_tax_rate' => '0']));

        $this->post(route('charges.store'), [
            'client_id' => Client::factory()->create()->id,
            'description' => 'Servicio exonerado',
            'currency' => 'PEN',
            'amount' => '100',
            'due_date' => '2026-10-20',
        ]);

        $this->assertTrue(Charge::sole()->tax_amount->isZero());
    }
}
