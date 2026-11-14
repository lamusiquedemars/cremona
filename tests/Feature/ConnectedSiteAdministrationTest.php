<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Services\ConnectedSiteAdministration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConnectedSiteAdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_only_exposes_the_administration_of_an_active_connected_site(): void
    {
        $organization = Organization::factory()->create();

        $this->assertNull(app(ConnectedSiteAdministration::class)->urlFor($organization));

        $organization->sites()->create([
            'name' => 'Contempo',
            'base_url' => 'https://contempoluthiers.fr/',
            'status' => 'active',
            'site_portal_enabled' => true,
        ]);

        $this->assertSame(
            'https://contempoluthiers.fr/admin',
            app(ConnectedSiteAdministration::class)->urlFor($organization),
        );
    }
}
