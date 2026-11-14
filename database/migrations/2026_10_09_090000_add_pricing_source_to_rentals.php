<?php

use App\Models\InstrumentCategory;
use App\Services\InstrumentRentalPricing;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rentals', function (Blueprint $table): void {
            $table->string('rental_pricing_source', 16)->default('recorded')->after('unit_amount');
        });

        // Les instruments saisis avant la création de leur grille n'ont pas à
        // être rouverts un par un : on rétablit ici leur rattachement direct.
        $pricing = app(InstrumentRentalPricing::class);
        InstrumentCategory::withoutGlobalScopes()->eachById(
            fn (InstrumentCategory $category): mixed => $pricing->synchronizeInstrumentsForCategory($category),
        );
    }

    public function down(): void
    {
        Schema::table('rentals', function (Blueprint $table): void {
            $table->dropColumn('rental_pricing_source');
        });
    }
};
