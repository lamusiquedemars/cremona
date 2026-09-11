<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('organizations')->orderBy('id')->eachById(function (object $organization): void {
            $organizationId = $organization->id;

            $modules = [
                'crm' => DB::table('people')->where('organization_id', $organizationId)->exists()
                    || DB::table('companies')->where('organization_id', $organizationId)->exists()
                    || DB::table('incoming_requests')->where('organization_id', $organizationId)->exists()
                    || DB::table('conversations')->where('organization_id', $organizationId)->exists()
                    || DB::table('crm_tasks')->where('organization_id', $organizationId)->exists(),
                'appointments' => DB::table('appointments')->where('organization_id', $organizationId)->exists(),
                'quotes' => DB::table('quotes')->where('organization_id', $organizationId)->exists(),
                'marketing' => DB::table('campaigns')->where('organization_id', $organizationId)->exists(),
            ];

            foreach ($modules as $module => $enabled) {
                DB::table('organization_modules')->insertOrIgnore([
                    'organization_id' => $organizationId,
                    'module' => $module,
                    'enabled' => $enabled,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        // Les réglages métier créés pendant la migration ne doivent pas être supprimés.
    }
};
