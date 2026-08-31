<?php

namespace Tests\Feature;

use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationTimezoneTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_organization_uses_its_own_valid_timezone(): void
    {
        $organization = Organization::factory()->create([
            'settings' => ['timezone' => 'America/Cuiaba'],
        ]);

        $this->assertSame('America/Cuiaba', $organization->timezone());
    }

    public function test_an_organization_falls_back_to_the_application_timezone_when_its_setting_is_invalid(): void
    {
        $organization = Organization::factory()->create([
            'settings' => ['timezone' => 'not-a-timezone'],
        ]);

        $this->assertSame(config('app.timezone'), $organization->timezone());
    }
}
