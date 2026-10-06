<?php

namespace App\Domain\Quotes\Actions;

use App\Domain\Billing\Enums\BillingType;
use App\Domain\Quotes\Enums\QuoteStatus;
use App\Domain\Shared\Audit\ActivityLogger;
use App\Domain\Shared\Documents\DocumentNumberGenerator;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Crea o actualiza una cotización con sus conceptos e importes ya calculados por QuoteCalculator.
 * El número (COT-AAAA-NNNN) se asigna al crear y nunca cambia, aunque se edite la fecha.
 */
class SaveQuote
{
    public const NUMBER_TYPE = 'quote';

    public const NUMBER_PREFIX = 'COT';

    public function __construct(
        private readonly DocumentNumberGenerator $numbers,
        private readonly ActivityLogger $logger,
    ) {}

    /**
     * @param  array<string, mixed>  $data  Validado por QuoteRequest.
     * @param  array<string, mixed>  $calculation  QuoteRequest::calculation().
     */
    public function handle(array $data, array $calculation, int $userId, ?Quote $quote = null): Quote
    {
        if ($quote !== null && ! $quote->status->isEditable()) {
            throw ValidationException::withMessages(['quote' => 'Solo se pueden editar cotizaciones en borrador o enviadas.']);
        }

        return DB::transaction(function () use ($data, $calculation, $userId, $quote) {
            $isNew = $quote === null;

            if ($isNew) {
                $year = CarbonImmutable::parse($data['issue_date'])->year;
                $number = $this->numbers->next(self::NUMBER_TYPE, self::NUMBER_PREFIX, $year);

                $quote = (new Quote)->forceFill([
                    'number' => $number['number'],
                    'year' => $year,
                    'sequence' => $number['sequence'],
                    'status' => QuoteStatus::Draft,
                    // ~285 bits aleatorios: el enlace público no se puede adivinar ni recorrer.
                    'public_token' => Str::random(48),
                    'created_by' => $userId,
                ]);
            }

            // La moneda antes que los importes (MoneyCast).
            $quote->forceFill(['currency' => $data['currency']])->forceFill([
                'client_id' => $data['client_id'],
                'issue_date' => $data['issue_date'],
                'valid_until' => $data['valid_until'],
                'delivery_date' => $data['delivery_date'],
                'tax_rate' => $data['tax_rate'],
                'global_discount_type' => $data['global_discount_type'],
                'global_discount_value' => $data['global_discount_value'],
                'subtotal' => $calculation['subtotal'],
                'global_discount_amount' => $calculation['global_discount'],
                'discount_total' => $calculation['discount_total'],
                'total' => $calculation['total'],
                'tax_base' => $calculation['tax_base'],
                'tax_amount' => $calculation['tax_amount'],
                'intro' => $data['intro'],
                'observations' => $data['observations'],
                'terms' => $data['terms'],
                'internal_notes' => $data['internal_notes'],
            ])->save();

            // validated() puede devolver los conceptos reordenados (según qué campos traen); el orden real es el índice enviado.
            $items = $data['items'];
            ksort($items);
            $this->replaceItems($quote, array_values($items), $calculation['lines']);

            $this->logger->log($isNew ? 'quote.created' : 'quote.updated', $quote, [
                'number' => $quote->number,
                'total' => $calculation['total'],
                'currency' => $quote->currency->value,
            ]);

            return $quote;
        });
    }

    /**
     * Los conceptos se reemplazan completos: mientras la cotización es editable, ningún otro registro los referencia.
     *
     * @param  list<array<string, mixed>>  $items
     * @param  list<array{gross: string, discount: string, total: string}>  $lines
     */
    private function replaceItems(Quote $quote, array $items, array $lines): void
    {
        $quote->items()->delete();

        $services = Service::withTrashed()->whereIn('id', array_filter(array_column($items, 'service_id')))->get()->keyBy('id');

        foreach ($items as $position => $item) {
            $service = isset($item['service_id']) ? $services->get($item['service_id']) : null;
            $recurring = $service?->default_billing_type === BillingType::Recurring;

            (new QuoteItem)->forceFill([
                'quote_id' => $quote->id,
                'service_id' => $service?->id,
                'position' => $position + 1,
                'name' => $item['name'],
                'description' => $item['description'] ?? null,
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'discount_type' => $item['discount_type'] ?? null,
                'discount_value' => $item['discount_value'] ?? null,
                'line_gross' => $lines[$position]['gross'],
                'line_discount' => $lines[$position]['discount'],
                'line_total' => $lines[$position]['total'],
                // Sugerencia para la conversión (Fase 9): la modalidad del catálogo en este momento.
                'suggested_billing_type' => $service?->default_billing_type,
                'suggested_interval_unit' => $recurring ? $service->default_interval_unit : null,
                'suggested_interval_count' => $recurring ? $service->default_interval_count : null,
            ])->save();
        }
    }
}
