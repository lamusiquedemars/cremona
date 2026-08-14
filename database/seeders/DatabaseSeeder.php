<?php

namespace Database\Seeders;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\OrganizationModule;
use App\Models\User;
use App\Tenancy\OrganizationContext;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $user = User::query()->updateOrCreate([
            'email' => 'admin@cremona.test',
        ], [
            'name' => 'Administration Cremona',
            'password' => 'password',
            'is_platform_admin' => true,
        ]);

        $organization = Organization::query()->updateOrCreate([
            'slug' => 'atelier-demo',
        ], [
            'name' => 'Atelier de démonstration',
            'vertical_pack' => 'luthier',
            'status' => 'active',
            'settings' => [],
        ]);

        $user->organizations()->syncWithoutDetaching([
            $organization->id => ['role' => OrganizationRole::Owner->value],
        ]);

        app(OrganizationContext::class)->run($organization, function (): void {
            foreach (['contacts', 'instruments', 'interventions', 'documents'] as $module) {
                OrganizationModule::query()->updateOrCreate(
                    ['module' => $module],
                    ['enabled' => true, 'configuration' => []],
                );
            }
        });
    }
}
