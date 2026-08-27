<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_payments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignUlid('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignUlid('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('payment_account_id')->nullable()->constrained('payment_accounts')->nullOnDelete();
            $table->string('method');
            $table->decimal('amount', 12, 2);
            $table->string('reference')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'branch_id', 'created_at']);
            $table->index(['business_id', 'sale_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_payments');
    }
};
