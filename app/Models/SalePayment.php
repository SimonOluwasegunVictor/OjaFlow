<?php

namespace App\Models;

use App\Enums\SalePaymentMethod;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalePayment extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'business_id', 'branch_id', 'sale_id', 'user_id', 'payment_account_id',
        'method', 'amount', 'reference', 'note',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(PaymentAccount::class, 'payment_account_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'method' => SalePaymentMethod::class,
            'amount' => 'decimal:2',
        ];
    }
}
