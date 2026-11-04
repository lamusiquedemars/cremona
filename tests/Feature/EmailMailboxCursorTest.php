<?php

namespace Tests\Feature;

use App\Models\EmailFolder;
use App\Models\EmailMailbox;
use App\Models\Organization;
use App\Models\OrganizationIntegration;
use App\Models\Conversation;
use App\Services\EmailMailboxSynchronizer;
use App\Tenancy\OrganizationContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;
use Webklex\PHPIMAP\Message as ImapMessage;

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

    public function test_a_regular_inbound_email_is_imported_without_a_website_form_contact(): void
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
            $message = \Mockery::mock(ImapMessage::class);
            $message->shouldReceive('getMessageId')->andReturn('<regular@example.test>');
            $message->shouldReceive('getInReplyTo')->andReturn('');
            $message->shouldReceive('getReferences')->andReturn(collect());
            $message->shouldReceive('getSubject')->andReturn('Question concernant un instrument');
            $message->shouldReceive('getTextBody')->andReturn('Bonjour, puis-je avoir un renseignement ?');
            $date = \Mockery::mock();
            $date->shouldReceive('toDate')->andReturn(CarbonImmutable::now());
            $message->shouldReceive('getDate')->andReturn($date);
            foreach (['getFrom', 'getTo', 'getCc', 'getReplyTo'] as $method) {
                $message->shouldReceive($method)->andReturn(collect());
            }

            $method = new ReflectionMethod(EmailMailboxSynchronizer::class, 'recordMessage');
            $method->invoke(app(EmailMailboxSynchronizer::class), $mailbox, $message, 'inbox');

            $conversation = Conversation::query()->sole();
            $this->assertNull($conversation->person_id);
            $this->assertSame('Question concernant un instrument', $conversation->subject);
        });
    }

    public function test_a_missing_configured_folder_is_reported_without_exposing_its_remote_name(): void
    {
        $method = new ReflectionMethod(EmailMailboxSynchronizer::class, 'failureMessage');

        $message = $method->invoke(
            app(EmailMailboxSynchronizer::class),
            new \LogicException('Le dossier IMAP envoyés configuré est introuvable.'),
        );

        $this->assertSame('Le dossier IMAP envoyés configuré est introuvable.', $message);
    }
}
