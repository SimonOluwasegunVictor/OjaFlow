<?php

namespace App\Enums;

enum StockMovementType: string
{
    case PURCHASE = 'purchase';
    case SALE = 'sale';
    case RETURN = 'return';
    case DAMAGE = 'damage';
    case CORRECTION = 'correction';
    case MANUAL_ADJUSTMENT = 'manual_adjustment';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
