<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const LUTHIER_DEFAULTS = [
        'crm',
        'appointments',
        'quotes',
        'luthier_catalog',
        'workshop',
        'rentals',
        'inventory',
        'communications',
    ];

    private const LUTHIER_ONLY = [
        'luthier_catalog',
        'workshop',
        'rentals',
    ];

    public function up(): void
    {
        DB::table('organizations')->where('vertical_pack', 'luthier')->orderBy('id')->each(function (object $organization): void {
            foreach (self::LUTHIER_DEFAULTS as $module) {
                DB::table('organization_modules')->updateOrInsert(
                    ['organization_id' => $organization->id, 'module' => $module],
                    ['enabled' => true, 'updated_at' => now(), 'created_at' => now()],
                );
            }
        });

        DB::table('organizations')->whereNull('vertical_pack')->orderBy('id')->each(function (object $organization): void {
            DB::table('organization_modules')
                ->where('organization_id', $organization->id)
                ->whereIn('module', self::LUTHIER_ONLY)
                ->update(['enabled' => false, 'updated_at' => now()]);
        });
    }

    public function down(): void
    {
        // Les activations précédentes ne peuvent pas être reconstruites sans risquer de réactiver un module désactivé volontairement.
    }
};
