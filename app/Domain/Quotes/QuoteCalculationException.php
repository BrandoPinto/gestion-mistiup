<?php

namespace App\Domain\Quotes;

use RuntimeException;

/**
 * Error de cálculo atribuible a un campo concreto del formulario (p. ej. "items.2.discount_value").
 */
class QuoteCalculationException extends RuntimeException
{
    public function __construct(public readonly string $field, string $message)
    {
        parent::__construct($message);
    }
}
