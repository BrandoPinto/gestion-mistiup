<?php

namespace App\Domain\Contracts;

use App\Domain\Billing\Recurrence;
use App\Domain\Shared\Money\Currency;
use Carbon\CarbonImmutable;

/**
 * Condiciones validadas de un servicio contratado. Las crea ContractRequest (o la conversión de
 * cotizaciones en la Fase 9) y las consume SaveContract.
 */
final class ContractTerms
{
    public function __construct(
        public readonly int $clientId,
        public readonly ?int $serviceId,
        public readonly string $name,
        public readonly ?string $description,
        public readonly Currency $currency,
        /** Texto decimal validado ("350.00"). */
        public readonly string $price,
        /** null = pago único. */
        public readonly ?Recurrence $recurrence,
        public readonly CarbonImmutable $startDate,
        /** null = sin fecha de fin (o pago único). */
        public readonly ?int $termMonths,
        /** Primer ciclo cuyo cobro registrará el sistema (los anteriores se cobraron fuera). */
        public readonly int $firstCycle,
        /** Vencimiento de ese primer cobro; puede diferir de su fecha natural. */
        public readonly CarbonImmutable $firstChargeDate,
        public readonly ?string $notes,
        /** Concepto de cotización que originó el contrato (conversión, Fase 9). */
        public readonly ?int $quoteItemId = null,
    ) {}
}
