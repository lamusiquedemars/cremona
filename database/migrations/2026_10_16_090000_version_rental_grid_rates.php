<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_price_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('instrument_category_id')->constrained()->cascadeOnDelete();
            $table->decimal('monthly_amount', 12, 2);
            $table->date('effective_on');
            $table->timestamps();

            $table->unique(['instrument_category_id', 'effective_on'], 'rental_price_versions_effective_unique');
            $table->index(['organization_id', 'effective_on'], 'rental_price_versions_due_idx');
        });

        Schema::table('instrument_categories', function (Blueprint $table): void {
            $table->date('rental_price_effective_on')->nullable()->after('rental_monthly_amount');
        });

        DB::table('instrument_categories')
            ->whereNotNull('rental_monthly_amount')
            ->orderBy('id')
            ->eachById(function (object $category): void {
                $effectiveOn = $category->created_at ? substr((string) $category->created_at, 0, 10) : now()->toDateString();

                DB::table('rental_price_versions')->insert([
                    'organization_id' => $category->organization_id,
                    'instrument_category_id' => $category->id,
                    'monthly_amount' => $category->rental_monthly_amount,
                    'effective_on' => $effectiveOn,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('instrument_categories')
                    ->where('id', $category->id)
                    ->update(['rental_price_effective_on' => $effectiveOn]);

                $instrumentIds = DB::table('instrument_assets')
                    ->where('instrument_category_id', $category->id)
                    ->pluck('id');

                if ($instrumentIds->isNotEmpty()) {
                    DB::table('rentals')
                        ->whereIn('instrument_asset_id', $instrumentIds)
                        ->where('rental_pricing_source', 'grid')
                        ->update(['unit_amount' => $category->rental_monthly_amount, 'updated_at' => now()]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('instrument_categories', function (Blueprint $table): void {
            $table->dropColumn('rental_price_effective_on');
        });

        Schema::dropIfExists('rental_price_versions');
    }
};
