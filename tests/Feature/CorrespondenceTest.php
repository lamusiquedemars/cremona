<?php

namespace Tests\Feature;

use App\Enums\ConversationStatus;
use App\Enums\MessageDirection;
use App\Enums\MessageParticipantRole;
use App\Enums\MessageThreadingStatus;
use App\Enums\MessageTransportStatus;
use App\Enums\OrganizationRole;
use App\Filament\Resources\Conversations\ConversationResource;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\ConversationUserState;
use App\Models\MessageAttachment;
use App\Models\MessageParticipant;
use App\Models\MessageReference;
use App\Models\MessageThreadCandidate;
use App\Models\Organization;
use App\Models\User;
use App\Services\CorrespondenceManager;
use App\Services\IncomingRequestManager;
use App\Tenancy\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class CorrespondenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_receiving_a_request_creates_an_idempotent_conversation_and_initial_message(): void
    {
        $organization = Organization::factory()->create();

        app(OrganizationContext::class)->run($organization, function (): void {
            $request = app(IncomingRequestManager::class)->receive([
                'idempotency_key' => 'site-a:submission-42',
                'name_snapshot' => 'Ada Lovelace',
                'email_snapshot' => 'ada@example.test',
                'subject' => 'Projet de restauration',
                'message' => 'Je souhaite échanger sur mon projet.',
            ]);

            $conversation = $request->conversation()->sole();
            $message = $conversation->messages()->sole();

            $this->assertSame(ConversationStatus::Open, $conversation->status);
            $this->assertSame($request->id, $conversation->incoming_request_id);
            $this->assertSame(MessageDirection::Inbound, $message->direction);
            $this->assertSame(MessageTransportStatus::Received, $message->transport_status);
            $this->assertSame(MessageThreadingStatus::Matched, $message->threading_status);
            $this->assertSame('Je souhaite échanger sur mon projet.', $message->body_text);
            $this->assertSame('ada@example.test', $message->participants()->sole()->address);

            app(IncomingRequestManager::class)->receive([
                'idempotency_key' => 'site-a:submission-42',
                'name_snapshot' => 'Ada Lovelace',
                'email_snapshot' => 'ada@example.test',
                'subject' => 'Projet de restauration',
                'message' => 'Je souhaite échanger sur mon projet.',
            ]);

            $this->assertSame(1, Conversation::query()->count());
            $this->assertSame(1, ConversationMessage::query()->count());
        });
    }

    public function test_a_fake_transport_accepts_a_reply_without_claiming_delivery(): void
    {
        $organization = Organization::factory()->create();
        $author = User::factory()->create();
        $author->organizations()->attach($organization);

        app(OrganizationContext::class)->run($organization, function () use ($author): void {
            $conversation = Conversation::query()->create(['initial_channel' => 'website']);
            $manager = app(CorrespondenceManager::class);
            $draft = $manager->createDraftReply($conversation, 'Bonjour Ada.', [[
                'role' => MessageParticipantRole::To,
                'address' => 'ada@example.test',
            ]], $author, 'Votre demande');

            $this->assertSame(MessageTransportStatus::Draft, $draft->transport_status);
            $this->assertSame('Re: Votre demande', $draft->subject);

            $manager->sendDraft($draft, $author);

            $draft->refresh();
            $conversation->refresh();
            $this->assertSame(MessageTransportStatus::Accepted, $draft->transport_status);
            $this->assertNotNull($draft->accepted_at);
            $this->assertStringEndsWith('@cremona.test>', $draft->message_id);
            $this->assertSame(ConversationStatus::WaitingCustomer, $conversation->status);
        });
    }

    public function test_an_ambiguous_message_can_be_linked_only_inside_its_organization_and_is_audited(): void
    {
        $first = Organization::factory()->create();
        $second = Organization::factory()->create();
        $actor = User::factory()->create();
        $actor->organizations()->attach($first);
        $context = app(OrganizationContext::class);

        $message = $context->run($first, fn () => app(CorrespondenceManager::class)->recordInbound(null, [
            'body_text' => 'Pouvez-vous confirmer le rendez-vous ?',
        ]));
        $conversation = $context->run($first, fn () => Conversation::query()->create(['initial_channel' => 'email']));

        $this->assertSame(MessageThreadingStatus::Unmatched, $message->threading_status);
        $context->run($first, fn () => app(CorrespondenceManager::class)->proposeThreadCandidate(
            $message,
            $conversation,
            0.81,
            'subject_and_participants',
        ));
        $context->run($first, function () use ($message): void {
            $this->assertSame(MessageThreadingStatus::Ambiguous, $message->refresh()->threading_status);
            $this->assertSame(1, $message->threadCandidates()->count());
        });

        $context->run($first, fn () => app(CorrespondenceManager::class)->linkMessage($message, $conversation, $actor));
        $this->assertSame($conversation->id, $message->refresh()->conversation_id);
        $this->assertSame(MessageThreadingStatus::Matched, $message->threading_status);

        $otherConversation = $context->run($second, fn () => Conversation::query()->create(['initial_channel' => 'email']));

        $this->expectException(LogicException::class);

        $context->run($first, fn () => app(CorrespondenceManager::class)->linkMessage($message, $otherConversation, $actor));
    }

    public function test_the_correspondence_interface_obeys_the_validated_permissions(): void
    {
        $organization = Organization::factory()->create();
        $viewer = User::factory()->create();
        $collaborator = User::factory()->create();
        $viewer->organizations()->attach($organization, ['role' => OrganizationRole::Viewer->value]);
        $collaborator->organizations()->attach($organization, ['role' => OrganizationRole::Collaborator->value]);
        $conversation = app(OrganizationContext::class)->run(
            $organization,
            fn () => Conversation::query()->create(['initial_channel' => 'website', 'subject' => 'Question client']),
        );

        $this->actingAs($viewer)
            ->get(ConversationResource::getUrl('index', tenant: $organization))
            ->assertOk()
            ->assertSee('Question client');
        $this->actingAs($viewer)
            ->get(ConversationResource::getUrl('view', ['record' => $conversation], tenant: $organization))
            ->assertOk()
            ->assertDontSee('Répondre');
        $this->actingAs($collaborator)
            ->get(ConversationResource::getUrl('view', ['record' => $conversation], tenant: $organization))
            ->assertOk()
            ->assertSee('Répondre');
    }

    public function test_every_correspondence_record_is_isolated_and_attachments_cannot_use_public_storage(): void
    {
        $first = Organization::factory()->create();
        $second = Organization::factory()->create();
        $user = User::factory()->create();
        $user->organizations()->attach($first);
        $context = app(OrganizationContext::class);

        $context->run($first, function () use ($user): void {
            $conversation = Conversation::query()->create(['initial_channel' => 'email']);
            $message = app(CorrespondenceManager::class)->recordInbound($conversation, [
                'body_text' => 'Message privé.',
                'participants' => [[
                    'role' => MessageParticipantRole::From,
                    'address' => 'sender@example.test',
                ]],
                'references' => ['<initial@example.test>'],
            ]);
            $message->attachments()->create([
                'disk' => 'private',
                'path' => 'correspondence/example.pdf',
                'original_name' => 'example.pdf',
                'size' => 12,
                'sha256' => hash('sha256', 'example'),
            ]);
            $message->threadCandidates()->create([
                'conversation_id' => $conversation->id,
                'confidence' => 0.5,
                'reason' => 'subject_and_participants',
            ]);
            $conversation->userStates()->create(['user_id' => $user->id, 'last_read_message_id' => $message->id]);

            foreach ([
                Conversation::class,
                ConversationMessage::class,
                ConversationUserState::class,
                MessageParticipant::class,
                MessageReference::class,
                MessageAttachment::class,
                MessageThreadCandidate::class,
            ] as $model) {
                $this->assertSame(1, $model::query()->count(), $model);
            }

            try {
                $message->attachments()->create([
                    'disk' => 'public',
                    'path' => 'unsafe.pdf',
                    'original_name' => 'unsafe.pdf',
                    'size' => 12,
                    'sha256' => hash('sha256', 'unsafe'),
                ]);
                $this->fail('Public attachment storage must be rejected.');
            } catch (LogicException) {
                $this->assertSame(1, MessageAttachment::query()->count());
            }
        });

        $context->run($second, function (): void {
            foreach ([
                Conversation::class,
                ConversationMessage::class,
                ConversationUserState::class,
                MessageParticipant::class,
                MessageReference::class,
                MessageAttachment::class,
                MessageThreadCandidate::class,
            ] as $model) {
                $this->assertSame(0, $model::query()->count(), $model);
            }
        });
    }
}
