<?php

namespace Tests\Feature;

use App\Models\EmailFolder;
use App\Models\EmailMailbox;
use App\Models\Organization;
use App\Models\OrganizationIntegration;
use App\Services\EmailMailboxSynchronizer;
use App\Tenancy\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class EmailMailboxCursorTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_folder_bootstraps_but_a_known_cursor_only_resets_when_uid_validity_changes(): void
    {
        $organization = Organization::factory()->create();
        app(OrganizationContext::class)->run($organization, function (): void {
            $integration = OrganizationIntegration::query()->create([
                'provider' => 'email',
                'name' => 'Boîte de test',
                'credentials' => [],
                'status' => 'active',
            ]);
            $mailbox = EmailMailbox::query()->create([
                'organization_integration_id' => $integration->id,
                'address' => 'bonjour@example.test',
                'status' => 'active',
            ]);

            $folder = EmailFolder::query()->create([
                'email_mailbox_id' => $mailbox->id,
                'remote_name' => 'INBOX',
                'role' => 'inbox',
            ]);

            $method = new ReflectionMethod(EmailMailboxSynchronizer::class, 'shouldBootstrap');
            $synchronizer = app(EmailMailboxSynchronizer::class);

            $this->assertTrue($method->invoke($synchronizer, $folder, '123'));

            $folder->update(['last_uid' => 42]);
            $this->assertFalse($method->invoke($synchronizer, $folder->fresh(), '123'));

            $folder->update(['uid_validity' => '123']);
            $this->assertFalse($method->invoke($synchronizer, $folder->fresh(), '123'));
            $this->assertTrue($method->invoke($synchronizer, $folder->fresh(), '456'));
        });
    }
}
