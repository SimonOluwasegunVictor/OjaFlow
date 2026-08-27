<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentAccount extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'business_id', 'type', 'name', 'provider', 'account_name',
        'account_number', 'terminal_id', 'is_active',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function salePayments(): HasMany
    {
        return $this->hasMany(SalePayment::class);
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
