<?php

namespace App\Domain\Quotes\Pdf;

/**
 * Formato de textos para la plantilla PDF (equivalente a lib/money.ts y lib/dates.ts del frontend).
 * Trabaja sobre strings decimales: nunca convierte importes a float.
 */
final class PdfFormat
{
    private const MONTHS = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

    private const SYMBOLS = ['PEN' => 'S/', 'USD' => '$'];

    public static function money(string $amount, string $currency): string
    {
        $negative = str_starts_with($amount, '-');
        [$whole, $fraction] = explode('.', ltrim($amount, '-')) + [1 => '00'];
        $grouped = preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $whole);

        return ($negative ? '-' : '').(self::SYMBOLS[$currency] ?? $currency).' '.$grouped.'.'.str_pad($fraction, 2, '0');
    }

    /** "2026-11-10" → "10 de noviembre de 2026". */
    public static function date(string $date): string
    {
        [$year, $month, $day] = array_map('intval', explode('-', substr($date, 0, 10)));

        return $day.' de '.self::MONTHS[$month - 1].' de '.$year;
    }

    /** "1.50" → "1.5", "2.00" → "2". */
    public static function number(string $value): string
    {
        return str_contains($value, '.') ? rtrim(rtrim($value, '0'), '.') : $value;
    }

    public static function discount(?string $type, ?string $value, string $currency): ?string
    {
        if (! $type || ! $value) {
            return null;
        }

        return $type === 'percent' ? '-'.self::number($value).'%' : '-'.self::money($value, $currency);
    }
}
