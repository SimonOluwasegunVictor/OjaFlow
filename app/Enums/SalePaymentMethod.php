<?php

namespace App\Enums;

enum SalePaymentMethod: string
{
    case CASH = 'cash';
    case TRANSFER = 'transfer';
    case POS = 'pos';
    case CREDIT = 'credit';
    case SPLIT = 'split';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
