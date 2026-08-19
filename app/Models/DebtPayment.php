<?php

namespace App\Models;

use App\Enums\SalePaymentMethod;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DebtPayment extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'business_id',
        'branch_id',
        'sale_id',
        'customer_id',
        'user_id',
        'amount',
        'payment_method',
        'note',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'payment_method' => SalePaymentMethod::class,
        ];
    }
}
