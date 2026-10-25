<?php

namespace Tests\Feature;

use App\Enums\OrganizationRole;
use App\Filament\Resources\EmailMailboxes\EmailMailboxResource;
use App\Models\EmailMailbox;
use App\Models\EmailSyncRun;
use App\Models\Organization;
use App\Models\OrganizationIntegration;
use App\Models\User;
use App\Services\OrganizationModuleRegistry;
use App\Tenancy\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailMailboxStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_email_mailbox_list_shows_the_outcome_of_the_latest_sync_run(): void
    {
        $organization = Organization::factory()->create();
        app(OrganizationModuleRegistry::class)->sync($organization, ['communications']);
        $user = User::factory()->create();
        $user->organizations()->attach($organization, ['role' => OrganizationRole::Administrator->value]);

        app(OrganizationContext::class)->run($organization, function (): void {
            $integration = OrganizationIntegration::query()->create([
                'provider' => 'imap_smtp',
                'name' => 'Boîte Atelier',
                'credentials' => [],
                'status' => 'active',
            ]);
            $mailbox = EmailMailbox::query()->create([
                'organization_integration_id' => $integration->id,
                'address' => 'bonjour@example.test',
                'status' => 'active',
                'last_synced_at' => now(),
            ]);
            EmailSyncRun::query()->create([
                'email_mailbox_id' => $mailbox->id,
                'status' => 'succeeded',
                'started_at' => now()->subMinute(),
                'finished_at' => now(),
                'imported_count' => 3,
                'skipped_count' => 7,
            ]);
        });

        $this->actingAs($user)
            ->get(EmailMailboxResource::getUrl('index', tenant: $organization))
            ->assertOk()
            ->assertSeeText('succeeded')
            ->assertSeeText('3 nouveau(x)')
            ->assertSeeText('7 déjà connu(s)');
    }

    public function test_an_administrator_can_open_an_existing_mailbox_without_exposing_its_passwords(): void
    {
        $organization = Organization::factory()->create();
        app(OrganizationModuleRegistry::class)->sync($organization, ['communications']);
        $user = User::factory()->create();
        $user->organizations()->attach($organization, ['role' => OrganizationRole::Administrator->value]);

        $mailbox = app(OrganizationContext::class)->run($organization, function (): EmailMailbox {
            $integration = OrganizationIntegration::query()->create([
                'provider' => 'email',
                'name' => 'mailbox',
                'credentials' => [
                    'imap_host' => 'imap.example.test', 'imap_port' => 993,
                    'imap_username' => 'bonjour@example.test', 'imap_password' => 'secret-imap',
                    'smtp_host' => 'smtp.example.test', 'smtp_port' => 465,
                    'smtp_username' => 'bonjour@example.test', 'smtp_password' => 'secret-smtp',
                ],
                'status' => 'active',
            ]);

            return EmailMailbox::query()->create([
                'organization_integration_id' => $integration->id,
                'address' => 'bonjour@example.test',
                'status' => 'active',
            ]);
        });

        $this->actingAs($user)
            ->get(EmailMailboxResource::getUrl('edit', ['record' => $mailbox], tenant: $organization))
            ->assertOk()
            ->assertSeeText('Boîte email bonjour@example.test')
            ->assertDontSee('secret-imap')
            ->assertDontSee('secret-smtp');
    }
}
