<?php

namespace App\Domain\Billing\Enums;

enum IntervalUnit: string
{
    case Month = 'month';
    case Year = 'year';
}
