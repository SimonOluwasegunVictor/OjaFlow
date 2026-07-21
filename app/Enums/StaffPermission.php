<?php

namespace App\Enums;

enum StaffPermission: string
{
    case VIEW_DASHBOARD = 'view_dashboard';
    case RECORD_SALES = 'record_sales';
    case VIEW_SALES = 'view_sales';
    case CANCEL_SALES = 'cancel_sales';
    case MANAGE_PRODUCTS = 'manage_products';
    case VIEW_STOCK = 'view_stock';
    case ADJUST_STOCK = 'adjust_stock';
    case MANAGE_CUSTOMERS = 'manage_customers';
    case RECORD_DEBT_PAYMENTS = 'record_debt_payments';
    case VIEW_REPORTS = 'view_reports';
    case PRINT_RECEIPTS = 'print_receipts';
    case MANAGE_STAFF = 'manage_staff';
    case MANAGE_SETTINGS = 'manage_settings';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
