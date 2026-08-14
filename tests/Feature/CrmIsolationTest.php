<?php

namespace Tests\Feature;

use App\Enums\ContactMethodType;
use App\Models\Company;
use App\Models\CompanyPerson;
use App\Models\ContactMethod;
use App\Models\IncomingRequest;
use App\Models\IncomingRequestActivity;
use App\Models\IncomingRequestAnswer;
use App\Models\IncomingRequestConsent;
use App\Models\Organization;
use App\Models\Person;
use App\Models\User;
use App\Services\IncomingRequestManager;
use App\Tenancy\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class CrmIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_crm_model_is_isolated_between_organizations(): void
    {
        $first = Organization::factory()->create();
        $second = Organization::factory()->create();
        $context = app(OrganizationContext::class);

        $this->createCrmGraph($first, 'first@example.test');
        $this->createCrmGraph($second, 'second@example.test');

        foreach ($this->scopedModels() as $model) {
            $this->assertSame(1, $context->run($first, fn () => $model::query()->count()), $model);
            $this->assertSame(1, $context->run($second, fn () => $model::query()->count()), $model);
            $this->assertSame(0, $model::query()->count(), $model);
        }
    }

    public function test_a_person_and_company_from_different_organizations_cannot_be_related(): void
    {
        $first = Organization::factory()->create();
        $second = Organization::factory()->create();
        $context = app(OrganizationContext::class);
        $person = $context->run($first, fn () => Person::query()->create(['display_name' => 'First Person']));
        $company = $context->run($second, fn () => Company::query()->create(['name' => 'Second Company']));

        $this->expectException(LogicException::class);

        $context->run($second, fn () => CompanyPerson::query()->create([
            'company_id' => $company->id,
            'person_id' => $person->id,
        ]));
    }

    public function test_a_nested_record_cannot_reference_another_organizations_request(): void
    {
        $first = Organization::factory()->create();
        $second = Organization::factory()->create();
        $request = app(OrganizationContext::class)->run(
            $first,
            fn () => app(IncomingRequestManager::class)->receive(['message' => 'First request']),
        );

        $this->expectException(LogicException::class);

        app(OrganizationContext::class)->run($second, fn () => IncomingRequestAnswer::query()->create([
            'incoming_request_id' => $request->id,
            'field_key' => 'phase',
            'label_snapshot' => 'Phase',
            'value' => 'private',
        ]));
    }

    public function test_an_assignee_must_belong_to_the_active_organization(): void
    {
        $first = Organization::factory()->create();
        $second = Organization::factory()->create();
        $outsider = User::factory()->create();
        $outsider->organizations()->attach($second);

        $this->expectException(LogicException::class);

        app(OrganizationContext::class)->run($first, fn () => Person::query()->create([
            'display_name' => 'Assigned incorrectly',
            'assigned_user_id' => $outsider->id,
        ]));
    }

    public function test_a_contact_method_cannot_reference_another_organizations_person(): void
    {
        $first = Organization::factory()->create();
        $second = Organization::factory()->create();
        $person = app(OrganizationContext::class)->run(
            $first,
            fn () => Person::query()->create(['display_name' => 'First Person']),
        );

        $this->expectException(LogicException::class);

        app(OrganizationContext::class)->run($second, function () use ($person): void {
            $method = new ContactMethod([
                'type' => ContactMethodType::Email,
                'value' => 'cross-tenant@example.test',
            ]);
            $method->contactable_type = 'person';
            $method->contactable_id = $person->id;
            $method->save();
        });
    }

    public function test_a_request_cannot_link_to_another_organizations_person(): void
    {
        $first = Organization::factory()->create();
        $second = Organization::factory()->create();
        $context = app(OrganizationContext::class);
        $person = $context->run($first, fn () => Person::query()->create(['display_name' => 'First Person']));

        $this->expectException(LogicException::class);

        $context->run($second, function () use ($person): void {
            $request = app(IncomingRequestManager::class)->receive(['message' => 'Second request']);
            $request->update(['person_id' => $person->id]);
        });
    }

    private function createCrmGraph(Organization $organization, string $email): void
    {
        app(OrganizationContext::class)->run($organization, function () use ($email, $organization): void {
            $person = Person::query()->create(['display_name' => $email]);
            $company = Company::query()->create(['name' => $email]);

            CompanyPerson::query()->create([
                'company_id' => $company->id,
                'person_id' => $person->id,
            ]);

            $person->contactMethods()->create([
                'type' => ContactMethodType::Email,
                'value' => $email,
            ]);

            app(IncomingRequestManager::class)->receive([
                'idempotency_key' => "submission-{$organization->id}",
                'email_snapshot' => $email,
                'message' => 'A request',
                'answers' => [[
                    'field_key' => 'phase',
                    'label_snapshot' => 'Phase',
                    'value' => 'example',
                ]],
                'consent' => [
                    'purpose' => 'respond_to_request',
                    'channel' => 'email',
                    'status' => 'granted',
                    'statement_snapshot' => 'I agree to be contacted about this request.',
                ],
            ]);
        });
    }

    /**
     * @return array<class-string>
     */
    private function scopedModels(): array
    {
        return [
            Person::class,
            Company::class,
            CompanyPerson::class,
            ContactMethod::class,
            IncomingRequest::class,
            IncomingRequestAnswer::class,
            IncomingRequestConsent::class,
            IncomingRequestActivity::class,
        ];
    }
}
