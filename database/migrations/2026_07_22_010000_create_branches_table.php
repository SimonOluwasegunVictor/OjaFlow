<?php

use App\Enums\BranchStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->enum('status', BranchStatus::values())->default(BranchStatus::ACTIVE->value);
            $table->boolean('is_main')->default(false);
            $table->timestamps();

            $table->unique(['business_id', 'name']);
            $table->index(['business_id', 'status']);
        });

        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'branch_id')) {
                $table->ulid('branch_id')->nullable()->after('business_id')->index();
            }

            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();
        });

        $now = now();

        DB::table('businesses')
            ->orderBy('id')
            ->get()
            ->each(function (object $business) use ($now): void {
                $branchId = (string) Str::ulid();

                DB::table('branches')->insert([
                    'id' => $branchId,
                    'business_id' => $business->id,
                    'name' => 'Main Branch',
                    'phone' => $business->phone,
                    'address' => $business->address,
                    'status' => BranchStatus::ACTIVE->value,
                    'is_main' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table('users')
                    ->where('business_id', $business->id)
                    ->whereNull('branch_id')
                    ->update([
                        'branch_id' => $branchId,
                        'updated_at' => $now,
                    ]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropColumn('branch_id');
        });

        Schema::dropIfExists('branches');
    }
};
