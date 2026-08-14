<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationModule;
use App\Tenancy\OrganizationContext;
use App\Tenancy\Scopes\OrganizationScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class OrganizationIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_scoped_records_are_invisible_without_an_active_organization(): void
    {
        $organization = Organization::factory()->create();
        $context = app(OrganizationContext::class);

        $context->run($organization, fn () => OrganizationModule::create([
            'module' => 'contacts',
            'enabled' => true,
        ]));

        $this->assertSame(0, OrganizationModule::query()->count());
    }

    public function test_each_organization_only_sees_its_own_records(): void
    {
        $first = Organization::factory()->create();
        $second = Organization::factory()->create();
        $context = app(OrganizationContext::class);

        $context->run($first, fn () => OrganizationModule::create([
            'module' => 'contacts',
            'enabled' => true,
        ]));

        $context->run($second, fn () => OrganizationModule::create([
            'module' => 'legal-cases',
            'enabled' => true,
        ]));

        $this->assertSame(
            ['contacts'],
            $context->run($first, fn () => OrganizationModule::pluck('module')->all()),
        );
        $this->assertSame(
            ['legal-cases'],
            $context->run($second, fn () => OrganizationModule::pluck('module')->all()),
        );
        $this->assertSame(2, OrganizationModule::withoutGlobalScope(OrganizationScope::class)->count());
    }

    public function test_the_active_organization_cannot_be_overridden_during_creation(): void
    {
        $first = Organization::factory()->create();
        $second = Organization::factory()->create();
        $context = app(OrganizationContext::class);

        $module = $context->run($first, fn () => OrganizationModule::create([
            'organization_id' => $second->id,
            'module' => 'contacts',
            'enabled' => true,
        ]));

        $this->assertSame($first->id, $module->organization_id);
    }

    public function test_scoped_records_cannot_be_created_without_an_active_organization(): void
    {
        $this->expectException(LogicException::class);

        OrganizationModule::create([
            'module' => 'contacts',
            'enabled' => true,
        ]);
    }
}
