<?php

namespace App\Models;

use App\Enums\SalePaymentMethod;
use App\Enums\SalePaymentStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'business_id',
        'branch_id',
        'customer_id',
        'user_id',
        'cart_id',
        'order_number',
        'subtotal',
        'discount',
        'total',
        'amount_paid',
        'balance_due',
        'payment_method',
        'payment_status',
        'due_date',
        'paid_at',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function debtPayments(): HasMany
    {
        return $this->hasMany(DebtPayment::class);
    }

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'total' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'balance_due' => 'decimal:2',
            'payment_method' => SalePaymentMethod::class,
            'payment_status' => SalePaymentStatus::class,
            'due_date' => 'date',
            'paid_at' => 'datetime',
        ];
    }
}
