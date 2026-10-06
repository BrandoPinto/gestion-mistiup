<?php

namespace Tests\Feature\Quotes;

use App\Models\Client;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicQuoteTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Quote $quote;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-06 15:00:00', 'UTC'));
        $this->user = User::factory()->create();
        $client = Client::factory()->create(['name' => 'Empresa ABC']);

        $this->actingAs($this->user)->post(route('quotes.store'), [
            'client_id' => $client->id,
            'currency' => 'PEN',
            'issue_date' => '2026-10-06',
            'valid_until' => '2026-10-21',
            'tax_rate' => '18',
            'internal_notes' => 'Margen bajo: no bajar más el precio.',
            'items' => [['name' => 'Desarrollo web', 'quantity' => '1', 'unit_price' => '2500']],
        ]);

        $this->quote = Quote::sole();
        auth()->logout();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_client_can_view_the_quote_without_login_and_nothing_internal_leaks(): void
    {
        $response = $this->get($this->quote->publicUrl());

        $response->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertInertia(fn (Assert $page) => $page
                ->component('public/Quote')
                ->where('document.number', 'COT-2026-0001')
                ->where('document.total', '2500.00')
                ->missing('document.internal_notes')
                ->missing('document.public_token')
                ->missing('document.id'));

        $this->assertStringNotContainsString('Margen bajo', $response->getContent());
    }

    public function test_guessing_tokens_or_ids_returns_404(): void
    {
        $this->get('/q/'.$this->quote->id)->assertNotFound();
        $this->get('/q/'.str_repeat('a', 48))->assertNotFound();
        $this->get('/q/'.substr($this->quote->public_token, 0, 47).'X')->assertNotFound();
        $this->get('/q/'.$this->quote->public_token.'%27%20OR%201=1')->assertNotFound();
    }

    public function test_the_public_page_does_not_allow_any_action(): void
    {
        $this->post($this->quote->publicUrl())->assertStatus(405);
        $this->post(route('quotes.send', $this->quote))->assertRedirect(route('login'));
        $this->get(route('quotes.show', $this->quote))->assertRedirect(route('login'));
    }

    public function test_views_are_recorded_for_clients_once_per_session_window(): void
    {
        $this->get($this->quote->publicUrl());
        $this->get($this->quote->publicUrl()); // Recarga inmediata: misma visita.

        $quote = $this->quote->fresh();
        $this->assertSame(1, $quote->view_count);
        $this->assertNotNull($quote->first_viewed_at);
        $this->assertSame('viewed', $quote->displayStatus(Carbon::parse('2026-10-06')->toImmutable()));

        $this->travel(31)->minutes();
        $this->get($this->quote->publicUrl());
        $this->assertSame(2, $this->quote->fresh()->view_count);
    }

    public function test_staff_visits_are_not_counted(): void
    {
        $this->actingAs($this->user)->get($this->quote->publicUrl())->assertOk();

        $this->assertSame(0, $this->quote->fresh()->view_count);
        $this->assertNull($this->quote->fresh()->first_viewed_at);
    }

    public function test_disabled_and_regenerated_links_stop_working(): void
    {
        $oldUrl = $this->quote->publicUrl();

        $this->actingAs($this->user)->patch(route('quotes.link.toggle', $this->quote), ['enabled' => false]);
        auth()->logout();
        $this->get($oldUrl)->assertNotFound();
        $this->get(route('quotes.public.pdf', $this->quote->public_token))->assertNotFound();

        $this->actingAs($this->user)->post(route('quotes.link.regenerate', $this->quote));
        auth()->logout();
        $this->get($oldUrl)->assertNotFound();
        $this->get($this->quote->fresh()->publicUrl())->assertOk();
    }

    public function test_cancelled_quote_shows_a_notice_instead_of_disappearing(): void
    {
        $this->actingAs($this->user)->post(route('quotes.cancel', $this->quote));
        auth()->logout();

        $this->get($this->quote->publicUrl())->assertInertia(fn (Assert $page) => $page->where('cancelled', true));
    }

    public function test_pdf_is_generated_for_the_client_and_the_admin(): void
    {
        $public = $this->get(route('quotes.public.pdf', $this->quote->public_token));
        $public->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $public->getContent());
        $this->assertStringContainsString('COT-2026-0001.pdf', $public->headers->get('Content-Disposition'));

        $this->actingAs($this->user)->get(route('quotes.pdf', [$this->quote, 'download' => 1]))
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="COT-2026-0001.pdf"');
    }

    public function test_admin_pdf_requires_login(): void
    {
        $this->get(route('quotes.pdf', $this->quote))->assertRedirect(route('login'));
    }

    public function test_public_link_is_rate_limited(): void
    {
        foreach (range(1, 60) as $attempt) {
            $this->get('/q/'.str_repeat('b', 48));
        }

        $this->get($this->quote->publicUrl())->assertStatus(429);
    }
}
