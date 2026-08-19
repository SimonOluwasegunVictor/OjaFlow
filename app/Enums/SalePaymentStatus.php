<?php

namespace App\Enums;

enum SalePaymentStatus: string
{
    case PAID = 'paid';
    case PARTIAL = 'partial';
    case OUTSTANDING = 'outstanding';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
