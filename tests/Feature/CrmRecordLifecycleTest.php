<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Organization;
use App\Models\Person;
use App\Services\CrmRecordManager;
use App\Tenancy\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class CrmRecordLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_contacts_and_companies_can_be_archived_and_reactivated_without_deletion(): void
    {
        $organization = Organization::factory()->create();

        app(OrganizationContext::class)->run($organization, function (): void {
            $person = Person::query()->create(['display_name' => 'Contact conservé']);
            $company = Company::query()->create(['name' => 'Entreprise conservée']);
            $manager = app(CrmRecordManager::class);

            $manager->archive($person);
            $manager->archive($company);

            $this->assertSame('archived', $person->refresh()->status);
            $this->assertNotNull($person->archived_at);
            $this->assertSame('archived', $company->refresh()->status);
            $this->assertNotNull($company->archived_at);
            $this->assertSame(1, Person::query()->count());
            $this->assertSame(1, Company::query()->count());

            $manager->reactivate($person);
            $manager->reactivate($company);

            $this->assertSame('active', $person->refresh()->status);
            $this->assertNull($person->archived_at);
            $this->assertSame('active', $company->refresh()->status);
            $this->assertNull($company->archived_at);
        });
    }

    public function test_a_record_from_another_organization_cannot_be_archived(): void
    {
        $firstOrganization = Organization::factory()->create();
        $secondOrganization = Organization::factory()->create();
        $person = app(OrganizationContext::class)->run(
            $firstOrganization,
            fn () => Person::query()->create(['display_name' => 'Contact privé']),
        );

        $this->expectException(LogicException::class);

        app(OrganizationContext::class)->run(
            $secondOrganization,
            fn () => app(CrmRecordManager::class)->archive($person),
        );
    }
}
