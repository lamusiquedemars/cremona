<?php

namespace Tests\Feature;

use App\Http\Middleware\SetActiveOrganization;
use App\Models\Organization;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class OrganizationInterfaceLocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_organization_uses_its_configured_interface_locale(): void
    {
        $organization = Organization::factory()->create([
            'settings' => ['interface_locale' => 'pt_BR'],
        ]);

        $this->assertSame('pt_BR', $organization->interfaceLocale());
    }

    public function test_an_invalid_interface_locale_falls_back_to_french(): void
    {
        $organization = Organization::factory()->create([
            'settings' => ['interface_locale' => 'not-a-locale'],
        ]);

        $this->assertSame('fr', $organization->interfaceLocale());
    }

    public function test_the_tenant_middleware_applies_the_organization_locale_only_for_its_request(): void
    {
        $organization = Organization::factory()->create([
            'settings' => ['interface_locale' => 'pt_BR'],
        ]);

        Filament::setTenant($organization, isQuiet: true);

        try {
            $response = app(SetActiveOrganization::class)->handle(
                Request::create('/dashboard/'.$organization->slug),
                fn (): mixed => response(app()->getLocale()),
            );

            $this->assertSame('pt_BR', $response->getContent());
            $this->assertSame('fr', app()->getLocale());
        } finally {
            Filament::setTenant(null, isQuiet: true);
        }
    }
}
