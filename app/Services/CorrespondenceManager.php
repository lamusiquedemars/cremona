<?php

namespace App\Services;

use App\Contracts\CorrespondenceTransport;
use App\Enums\ConversationStatus;
use App\Enums\MessageChannel;
use App\Enums\MessageDirection;
use App\Enums\MessageParticipantRole;
use App\Enums\MessageThreadingStatus;
use App\Enums\MessageTransportStatus;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\EmailMailbox;
use App\Models\IncomingRequest;
use App\Models\MessageThreadCandidate;
use App\Models\User;
use App\Tenancy\OrganizationContext;
use Illuminate\Support\Facades\DB;
use LogicException;
use Throwable;

class CorrespondenceManager
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly CorrespondenceTransport $transport,
        private readonly OrganizationContext $context,
    ) {}

    public function createForIncomingRequest(IncomingRequest $request): Conversation
    {
        $this->assertOwned($request->organization_id);

        return DB::transaction(function () use ($request): Conversation {
            $conversation = Conversation::query()->firstOrCreate(
                ['incoming_request_id' => $request->getKey()],
                [
                    'person_id' => $request->person_id,
                    'company_id' => $request->company_id,
                    'assigned_user_id' => $request->assigned_user_id,
                    'initial_channel' => MessageChannel::Website,
                    'subject' => $request->subject,
                    'status' => ConversationStatus::Open,
                ],
            );

            if ($conversation->messages()->exists()) {
                return $conversation;
            }

            $message = $this->recordInbound($conversation, [
                'channel' => MessageChannel::Website,
                'subject' => $request->subject,
                'body_text' => $request->message,
                'idempotency_key' => "incoming-request:{$request->public_id}",
                'authored_at' => $request->received_at,
                'participants' => $request->email_snapshot !== null ? [[
                    'role' => MessageParticipantRole::From,
                    'name' => $request->name_snapshot,
                    'address' => $request->email_snapshot,
                ]] : [],
            ]);

            $this->auditLogger->record('correspondence.conversation_created', $conversation, metadata: [
                'incoming_request_id' => $request->getKey(),
                'message_id' => $message->getKey(),
            ]);

            return $conversation;
        });
    }

    /**
     * @param  array{channel?: MessageChannel, subject?: ?string, body_text: string, idempotency_key?: ?string, authored_at?: mixed, message_id?: ?string, in_reply_to?: ?string, participants?: array<int, array{role: MessageParticipantRole, name?: ?string, address: string, position?: int}>, references?: array<int, string>}  $attributes
     */
    public function recordInbound(?Conversation $conversation, array $attributes): ConversationMessage
    {
        if ($conversation !== null) {
            $this->assertOwned($conversation->organization_id);
        }

        return DB::transaction(function () use ($conversation, $attributes): ConversationMessage {
            $values = [
                'conversation_id' => $conversation?->getKey(),
                'direction' => MessageDirection::Inbound,
                'channel' => $attributes['channel'] ?? MessageChannel::Email,
                'subject' => $attributes['subject'] ?? null,
                'body_text' => $attributes['body_text'],
                'message_id' => $attributes['message_id'] ?? null,
                'in_reply_to' => $attributes['in_reply_to'] ?? null,
                'transport_status' => MessageTransportStatus::Received,
                'threading_status' => $conversation === null
                    ? MessageThreadingStatus::Unmatched
                    : MessageThreadingStatus::Matched,
                'idempotency_key' => $attributes['idempotency_key'] ?? null,
                'payload_fingerprint' => $this->fingerprint($attributes),
                'authored_at' => $attributes['authored_at'] ?? now(),
                'received_at' => now(),
            ];

            $message = ($values['idempotency_key'] ?? null) === null
                ? ConversationMessage::query()->create($values)
                : ConversationMessage::query()->firstOrCreate(
                    ['idempotency_key' => $values['idempotency_key']],
                    $values,
                );

            if (! $message->wasRecentlyCreated) {
                if (! hash_equals($message->payload_fingerprint, $values['payload_fingerprint'])) {
                    throw new LogicException('The message idempotency key was reused with different content.');
                }

                return $message;
            }

            foreach ($attributes['participants'] ?? [] as $participant) {
                $message->participants()->create([
                    'role' => $participant['role'],
                    'name' => $participant['name'] ?? null,
                    'address' => $participant['address'],
                    'position' => $participant['position'] ?? 0,
                ]);
            }

            foreach ($attributes['references'] ?? [] as $position => $reference) {
                $message->references()->create(['reference' => $reference, 'position' => $position]);
            }

            if ($conversation !== null) {
                $this->refreshTimeline($conversation, $message);
            }

            return $message;
        });
    }

    /**
     * @param  array<int, array{role: MessageParticipantRole, name?: ?string, address: string, position?: int}>  $participants
     */
    public function createDraftReply(
        Conversation $conversation,
        string $body,
        array $participants,
        User $author,
        ?string $subject = null,
    ): ConversationMessage {
        $this->assertOwned($conversation->organization_id);

        if ($participants === []) {
            throw new LogicException('A reply requires at least one recipient.');
        }

        $mailbox = EmailMailbox::query()->where('status', 'active')->first()
            ?? throw new LogicException('Aucune boîte email active n’est configurée pour cette organisation.');

        return DB::transaction(function () use ($conversation, $body, $participants, $author, $subject, $mailbox): ConversationMessage {
            $previous = $conversation->messages()->whereNotNull('message_id')->latest('authored_at')->first();
            $message = $conversation->messages()->create([
                'email_mailbox_id' => $mailbox->getKey(),
                'author_user_id' => $author->getKey(),
                'direction' => MessageDirection::Outbound,
                'channel' => MessageChannel::Email,
                'subject' => $this->replySubject($subject ?? $conversation->subject),
                'body_text' => $body,
                'in_reply_to' => $previous?->message_id,
                'transport_status' => MessageTransportStatus::Draft,
                'threading_status' => MessageThreadingStatus::Matched,
                'payload_fingerprint' => $this->fingerprint([
                    'conversation' => $conversation->getKey(),
                    'body' => $body,
                    'participants' => $participants,
                ]),
                'authored_at' => now(),
            ]);

            foreach ($participants as $participant) {
                $message->participants()->create([
                    'role' => $participant['role'],
                    'name' => $participant['name'] ?? null,
                    'address' => $participant['address'],
                    'position' => $participant['position'] ?? 0,
                ]);
            }

            if ($previous !== null) {
                $references = $previous->references()->orderBy('position')->pluck('reference')->all();
                $references[] = $previous->message_id;
                foreach (array_values(array_unique(array_filter($references))) as $position => $reference) {
                    $message->references()->create(['reference' => $reference, 'position' => $position]);
                }
            }

            $this->auditLogger->record('correspondence.reply_drafted', $message, $author, [
                'conversation_id' => $conversation->getKey(),
            ]);

            return $message->load('participants');
        });
    }

    private function replySubject(?string $subject): string
    {
        $subject = trim((string) $subject);

        return preg_match('/^(?:re|r[eé]p)\s*:/ui', $subject) === 1
            ? $subject
            : 'Re: '.$subject;
    }

    public function sendDraft(ConversationMessage $message, User $actor): ConversationMessage
    {
        $this->assertOwned($message->organization_id);

        if ($message->direction !== MessageDirection::Outbound
            || $message->transport_status !== MessageTransportStatus::Draft) {
            throw new LogicException('Only outgoing draft messages can be sent.');
        }

        $message->update(['transport_status' => MessageTransportStatus::Queued, 'queued_at' => now()]);

        try {
            $messageId = $this->transport->send($message->refresh());
            $message->update([
                'message_id' => $messageId,
                'transport_status' => MessageTransportStatus::Accepted,
                'accepted_at' => now(),
                'failure_code' => null,
                'failure_message' => null,
            ]);

            if ($message->conversation !== null) {
                $this->refreshTimeline($message->conversation, $message->refresh());
            }

            $this->auditLogger->record('correspondence.reply_accepted', $message, $actor, [
                'conversation_id' => $message->conversation_id,
            ]);

            return $message->refresh();
        } catch (Throwable $exception) {
            $message->update([
                'transport_status' => MessageTransportStatus::Failed,
                'failed_at' => now(),
                'failure_code' => class_basename($exception),
                'failure_message' => 'Transport failed. Retry the message or check the mailbox connection.',
            ]);

            $this->auditLogger->record('correspondence.reply_failed', $message, $actor, [
                'conversation_id' => $message->conversation_id,
                'failure_code' => class_basename($exception),
            ]);

            return $message->refresh();
        }
    }

    public function linkMessage(ConversationMessage $message, Conversation $conversation, User $actor): void
    {
        $this->assertOwned($message->organization_id);
        $this->assertOwned($conversation->organization_id);

        DB::transaction(function () use ($message, $conversation, $actor): void {
            $previousConversationId = $message->conversation_id;
            $message->update([
                'conversation_id' => $conversation->getKey(),
                'threading_status' => MessageThreadingStatus::Matched,
            ]);
            $message->threadCandidates()->update([
                'status' => 'rejected',
                'resolved_by_user_id' => $actor->getKey(),
                'resolved_at' => now(),
            ]);
            MessageThreadCandidate::query()
                ->where('conversation_message_id', $message->getKey())
                ->where('conversation_id', $conversation->getKey())
                ->update(['status' => 'selected', 'resolved_by_user_id' => $actor->getKey(), 'resolved_at' => now()]);

            $this->refreshTimeline($conversation, $message->refresh());
            $this->auditLogger->record('correspondence.message_linked', $message, $actor, [
                'from_conversation_id' => $previousConversationId,
                'to_conversation_id' => $conversation->getKey(),
            ]);
        });
    }

    public function proposeThreadCandidate(
        ConversationMessage $message,
        Conversation $conversation,
        float $confidence,
        string $reason,
    ): MessageThreadCandidate {
        $this->assertOwned($message->organization_id);
        $this->assertOwned($conversation->organization_id);

        if ($message->conversation_id !== null || $message->threading_status === MessageThreadingStatus::Ignored) {
            throw new LogicException('Only an unlinked message can receive threading candidates.');
        }

        $candidate = $message->threadCandidates()->updateOrCreate(
            ['conversation_id' => $conversation->getKey()],
            ['confidence' => $confidence, 'reason' => $reason, 'status' => 'proposed'],
        );
        $message->update(['threading_status' => MessageThreadingStatus::Ambiguous]);

        return $candidate;
    }

    public function markRead(Conversation $conversation, User $user): void
    {
        $this->assertOwned($conversation->organization_id);
        $lastMessage = $conversation->messages()->latest('authored_at')->first();

        $conversation->userStates()->updateOrCreate(
            ['user_id' => $user->getKey()],
            ['last_read_message_id' => $lastMessage?->getKey(), 'last_read_at' => now()],
        );
    }

    public function closeConversation(Conversation $conversation, User $actor): void
    {
        $this->assertOwned($conversation->organization_id);

        if ($conversation->status === ConversationStatus::Closed) {
            return;
        }

        $conversation->update(['status' => ConversationStatus::Closed, 'closed_at' => now()]);
        $this->auditLogger->record('correspondence.conversation_closed', $conversation, $actor);
    }

    private function refreshTimeline(Conversation $conversation, ConversationMessage $message): void
    {
        $occurredAt = $message->authored_at ?? now();
        $updates = [
            'first_message_at' => $conversation->first_message_at ?? $occurredAt,
            'last_message_at' => $conversation->last_message_at === null || $occurredAt->greaterThan($conversation->last_message_at)
                ? $occurredAt
                : $conversation->last_message_at,
        ];

        if ($message->direction === MessageDirection::Inbound) {
            $updates['last_inbound_at'] = $occurredAt;
            $updates['status'] = ConversationStatus::Open;
            $updates['closed_at'] = null;
        }

        if ($message->direction === MessageDirection::Outbound
            && $message->transport_status === MessageTransportStatus::Accepted) {
            $updates['last_outbound_at'] = $occurredAt;
            $updates['status'] = ConversationStatus::WaitingCustomer;
        }

        $conversation->update($updates);
    }

    private function assertOwned(int $organizationId): void
    {
        if ($organizationId !== $this->context->require()->getKey()) {
            throw new LogicException('The correspondence record does not belong to the active organization.');
        }
    }

    private function fingerprint(array $payload): string
    {
        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }
}
