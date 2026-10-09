<?php

use App\Models\InstrumentCategory;
use App\Services\InstrumentRentalPricing;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $pricing = app(InstrumentRentalPricing::class);

        InstrumentCategory::withoutGlobalScopes()->eachById(
            fn (InstrumentCategory $category): mixed => $pricing->synchronizeInstrumentsForCategory($category),
        );
    }

    public function down(): void
    {
        // Un rattachement de grille est une donnée métier conservée : aucun retour destructif.
    }
};
