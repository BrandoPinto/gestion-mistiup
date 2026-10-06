<?php

namespace App\Domain\Quotes\Actions;

use App\Domain\Contracts\Actions\SaveContract;
use App\Domain\Contracts\ContractTerms;
use App\Domain\Quotes\Enums\QuoteStatus;
use App\Domain\Shared\Audit\ActivityLogger;
use App\Models\ClientService;
use App\Models\Quote;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Convierte conceptos de una cotización en servicios contratados (y genera sus cobros dentro del horizonte).
 *
 * Sin duplicados: la cotización se bloquea durante la conversión, se verifica que ningún concepto tenga
 * ya un contrato y, como última garantía, la base de datos impide dos contratos del mismo concepto
 * (UNIQUE client_services.quote_item_id). Todo o nada: si un concepto falla, no se crea ninguno.
 */
class ConvertQuoteItems
{
    public function __construct(
        private readonly SaveContract $saveContract,
        private readonly ActivityLogger $logger,
    ) {}

    /**
     * @param  list<array{quote_item_id: int, terms: ContractTerms}>  $conversions
     * @return list<ClientService>
     */
    public function handle(Quote $quote, array $conversions): array
    {
        return DB::transaction(function () use ($quote, $conversions) {
            /** @var Quote $locked */
            $locked = Quote::query()->lockForUpdate()->findOrFail($quote->id);

            if (! $locked->status->isConvertible()) {
                throw ValidationException::withMessages(['quote' => 'Esta cotización ya no se puede convertir.']);
            }

            $itemIds = array_column($conversions, 'quote_item_id');
            $alreadyConverted = ClientService::withTrashed()->whereIn('quote_item_id', $itemIds)->exists();

            if ($alreadyConverted) {
                throw ValidationException::withMessages(['quote' => 'Alguno de los conceptos ya fue convertido. Recarga la página.']);
            }

            $contracts = array_map(fn (array $conversion) => $this->saveContract->handle($conversion['terms']), $conversions);

            $this->updateStatus($locked);

            $this->logger->log('quote.converted', $locked, [
                'number' => $locked->number,
                'contracts' => array_map(fn (ClientService $contract) => $contract->id, $contracts),
            ]);

            return $contracts;
        });
    }

    private function updateStatus(Quote $quote): void
    {
        $total = $quote->items()->count();
        $converted = ClientService::withTrashed()->whereIn('quote_item_id', $quote->items()->select('id'))->count();
        $complete = $converted >= $total;

        $quote->forceFill([
            'status' => $complete ? QuoteStatus::Converted : QuoteStatus::PartiallyConverted,
            'converted_at' => $complete ? now() : $quote->converted_at,
        ])->save();
    }
}
