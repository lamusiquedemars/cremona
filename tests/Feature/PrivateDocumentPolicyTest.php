<?php

namespace Tests\Feature;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\PrivateDocument;
use App\Models\User;
use App\Policies\PrivateDocumentPolicy;
use App\Tenancy\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrivateDocumentPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_restricted_documents_are_reserved_for_organization_administrators(): void
    {
        $organization = Organization::factory()->create();
        $administrator = User::factory()->create();
        $collaborator = User::factory()->create();
        $administrator->organizations()->attach($organization, ['role' => OrganizationRole::Administrator->value]);
        $collaborator->organizations()->attach($organization, ['role' => OrganizationRole::Collaborator->value]);

        $document = app(OrganizationContext::class)->run($organization, fn (): PrivateDocument => PrivateDocument::query()->create([
            'title' => 'Justificatif',
            'category' => 'identity_proof',
            'disk' => 'local',
            'path' => 'documents/test/identity.pdf',
            'original_name' => 'identity.pdf',
            'size' => 1,
            'sha256' => str_repeat('a', 64),
        ]));

        app(OrganizationContext::class)->run($organization, function () use ($administrator, $collaborator, $document): void {
            $policy = app(PrivateDocumentPolicy::class);

            $this->assertTrue($policy->view($administrator, $document));
            $this->assertFalse($policy->view($collaborator, $document));
        });
    }
}
