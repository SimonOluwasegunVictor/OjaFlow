<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasColumn('sales', 'cart_id')) {
            return;
        }

        Schema::table('sales', function (Blueprint $table) {
            $table->foreignUlid('cart_id')
                ->nullable()
                ->after('user_id')
                ->constrained('carts')
                ->nullOnDelete();

            $table->index(['business_id', 'cart_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasColumn('sales', 'cart_id')) {
            return;
        }

        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex(['business_id', 'cart_id']);
            $table->dropConstrainedForeignId('cart_id');
        });
    }
};
