<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $moduleSources = [
            'crm' => ['contacts', 'inquiries', 'conversations', 'tasks'],
            'appointments' => ['appointments'],
            'quotes' => ['quotes', 'documents'],
            'marketing' => ['acquisition'],
            'luthier_catalog' => ['instruments', 'interventions'],
            'workshop' => ['interventions'],
            'rentals' => ['rentals'],
            'inventory' => ['inventory'],
            'communications' => ['inquiries', 'conversations', 'appointments', 'contempo'],
        ];

        foreach (DB::table('organizations')->pluck('id') as $organizationId) {
            foreach ($moduleSources as $module => $sources) {
                $enabled = DB::table('organization_modules')
                    ->where('organization_id', $organizationId)
                    ->whereIn('module', $sources)
                    ->where('enabled', true)
                    ->exists();

                DB::table('organization_modules')->updateOrInsert(
                    ['organization_id' => $organizationId, 'module' => $module],
                    ['enabled' => $enabled, 'updated_at' => now(), 'created_at' => now()],
                );
            }
        }
    }

    public function down(): void
    {
        DB::table('organization_modules')->whereIn('module', [
            'crm',
            'appointments',
            'quotes',
            'marketing',
            'luthier_catalog',
            'workshop',
            'rentals',
            'inventory',
            'communications',
        ])->delete();
    }
};
