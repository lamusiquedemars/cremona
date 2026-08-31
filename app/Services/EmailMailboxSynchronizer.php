<?php

namespace App\Services;

use App\Enums\ConversationStatus;
use App\Enums\MessageChannel;
use App\Enums\MessageDirection;
use App\Enums\MessageParticipantRole;
use App\Enums\MessageThreadingStatus;
use App\Enums\MessageTransportStatus;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\EmailFolder;
use App\Models\EmailMailbox;
use App\Models\EmailMessageCopy;
use App\Support\TechnicalEmailNotification;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use LogicException;
use Throwable;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Message as ImapMessage;

class EmailMailboxSynchronizer
{
    private const BATCH_SIZE = 50;

    /** @return array{imported: int, skipped: int} */
    public function sync(EmailMailbox $mailbox): array
    {
        $lock = Cache::lock("email-mailbox-sync:{$mailbox->getKey()}", 300);

        if (! $lock->get()) {
            throw new LogicException('Une relève de cette boîte est déjà en cours.');
        }

        $run = $mailbox->syncRuns()->create(['status' => 'running', 'started_at' => now()]);
        $imported = 0;
        $skipped = 0;
        $client = null;

        try {
            $client = $this->connect($mailbox);
            foreach ($this->configuredFolders($mailbox) as $role => $remoteName) {
                $folder = $client->getFolder($remoteName);
                if ($folder === null) {
                    throw new LogicException("Le dossier IMAP « {$remoteName} » est introuvable.");
                }

                $trackedFolder = EmailFolder::query()->firstOrCreate(
                    ['email_mailbox_id' => $mailbox->getKey(), 'remote_name' => $remoteName],
                    ['role' => $role, 'sync_status' => 'idle'],
                );
                $trackedFolder->update(['sync_status' => 'syncing', 'last_error' => null, 'last_error_at' => null]);

                foreach ($folder->messages()->all()->setFetchOrderDesc()->limit(self::BATCH_SIZE)->get() as $message) {
                    [$created, $ignored] = $this->import($mailbox, $trackedFolder, $message, $role);
                    $imported += $created;
                    $skipped += $ignored;
                }

                $trackedFolder->update([
                    'sync_status' => 'idle',
                    'last_synced_at' => now(),
                ]);
            }

            $mailbox->update([
                'status' => 'active',
                'last_synced_at' => now(),
                'last_error_at' => null,
                'last_error' => null,
            ]);
            $run->update([
                'status' => 'succeeded',
                'finished_at' => now(),
                'folders_count' => count($this->configuredFolders($mailbox)),
                'imported_count' => $imported,
                'skipped_count' => $skipped,
            ]);

            return compact('imported', 'skipped');
        } catch (Throwable $exception) {
            $message = 'La relève IMAP a échoué. Vérifie la boîte ou réessaie plus tard.';
            $mailbox->update(['status' => 'degraded', 'last_error_at' => now(), 'last_error' => $message]);
            $run->update(['status' => 'failed', 'finished_at' => now(), 'error_message' => $message]);
            throw $exception;
        } finally {
            $client?->disconnect();
            $lock->release();
        }
    }

    private function connect(EmailMailbox $mailbox): object
    {
        $credentials = $mailbox->integration->credentials;
        foreach (['imap_host', 'imap_port', 'imap_username', 'imap_password'] as $key) {
            if (blank($credentials[$key] ?? null)) {
                throw new LogicException('La configuration IMAP de cette boîte est incomplète.');
            }
        }

        $client = (new ClientManager)->make([
            'host' => $credentials['imap_host'],
            'port' => (int) $credentials['imap_port'],
            'encryption' => 'ssl',
            'validate_cert' => true,
            'protocol' => 'imap',
            'username' => $credentials['imap_username'],
            'password' => $credentials['imap_password'],
            'authentication' => 'plain',
        ]);
        $client->connect();

        return $client;
    }

    /** @return array<string, string> */
    private function configuredFolders(EmailMailbox $mailbox): array
    {
        return array_filter([
            'inbox' => $mailbox->inbox_folder,
            'sent' => $mailbox->sent_folder,
        ]);
    }

    /** @return array{0: int, 1: int} */
    private function import(EmailMailbox $mailbox, EmailFolder $folder, ImapMessage $source, string $role): array
    {
        if (TechnicalEmailNotification::isSource($mailbox, $source, $role)) {
            return [0, 1];
        }

        $uid = (int) $source->getUid();
        if (EmailMessageCopy::query()->where('email_folder_id', $folder->getKey())->where('uid', $uid)->exists()) {
            return [0, 1];
        }

        return DB::transaction(function () use ($mailbox, $folder, $source, $role, $uid): array {
            $messageId = trim((string) $source->getMessageId());
            $existing = $messageId === '' ? null : ConversationMessage::query()
                ->where('message_id_hash', ConversationMessage::headerHash(ConversationMessage::canonicalHeaderId($messageId)))
                ->first();

            $message = $existing ?? $this->recordMessage($mailbox, $source, $role);
            EmailMessageCopy::query()->create([
                'email_folder_id' => $folder->getKey(),
                'conversation_message_id' => $message->getKey(),
                'uid' => $uid,
                'synchronized_at' => now(),
            ]);
            $folder->update(['last_uid' => max((int) $folder->last_uid, $uid)]);

            return [$existing === null ? 1 : 0, $existing === null ? 0 : 1];
        });
    }

    private function recordMessage(EmailMailbox $mailbox, ImapMessage $source, string $role): ConversationMessage
    {
        $messageId = trim((string) $source->getMessageId()) ?: null;
        $inReplyTo = trim((string) $source->getInReplyTo()) ?: null;
        $references = array_values(array_filter(array_map('strval', $source->getReferences()->toArray())));
        $conversation = $this->findConversation($inReplyTo, $references)
            ?? Conversation::query()->create([
                'initial_channel' => MessageChannel::Email,
                'subject' => (string) $source->getSubject() ?: null,
                'status' => ConversationStatus::Open,
            ]);
        $authoredAt = $this->authoredAt($source);
        $direction = $role === 'sent' ? MessageDirection::Outbound : MessageDirection::Inbound;
        $message = $conversation->messages()->create([
            'email_mailbox_id' => $mailbox->getKey(),
            'direction' => $direction,
            'channel' => MessageChannel::Email,
            'subject' => (string) $source->getSubject() ?: null,
            'body_text' => $this->body($source),
            'message_id' => $messageId,
            'in_reply_to' => $inReplyTo,
            'transport_status' => $direction === MessageDirection::Inbound ? MessageTransportStatus::Received : MessageTransportStatus::Accepted,
            'threading_status' => MessageThreadingStatus::Matched,
            'payload_fingerprint' => hash('sha256', implode('|', [$messageId, $inReplyTo, $this->body($source)])),
            'authored_at' => $authoredAt,
            'received_at' => $direction === MessageDirection::Inbound ? now() : null,
            'accepted_at' => $direction === MessageDirection::Outbound ? $authoredAt : null,
        ]);

        foreach ($this->participants($source) as $participant) {
            $message->participants()->create($participant);
        }
        foreach ($references as $position => $reference) {
            $message->references()->create(['reference' => $reference, 'position' => $position]);
        }

        $this->refreshConversation($conversation, $message);

        return $message;
    }

    private function findConversation(?string $inReplyTo, array $references): ?Conversation
    {
        foreach (array_filter(array_merge([$inReplyTo], array_reverse($references))) as $reference) {
            $match = ConversationMessage::query()
                ->where('message_id_hash', ConversationMessage::headerHash(ConversationMessage::canonicalHeaderId($reference)))
                ->whereNotNull('conversation_id')
                ->first();
            if ($match?->conversation !== null) {
                return $match->conversation;
            }
        }

        return null;
    }

    private function refreshConversation(Conversation $conversation, ConversationMessage $message): void
    {
        $date = $message->authored_at ?? now();
        $conversation->update([
            'first_message_at' => $conversation->first_message_at === null || $date->lt($conversation->first_message_at) ? $date : $conversation->first_message_at,
            'last_message_at' => $conversation->last_message_at === null || $date->gt($conversation->last_message_at) ? $date : $conversation->last_message_at,
            'last_inbound_at' => $message->direction === MessageDirection::Inbound ? $date : $conversation->last_inbound_at,
            'last_outbound_at' => $message->direction === MessageDirection::Outbound ? $date : $conversation->last_outbound_at,
            'status' => $message->direction === MessageDirection::Inbound ? ConversationStatus::Open : ConversationStatus::WaitingCustomer,
        ]);
    }

    private function body(ImapMessage $message): string
    {
        $body = trim($message->getTextBody());
        if ($body === '') {
            $body = trim(strip_tags($message->getHTMLBody()));
        }

        return $body !== '' ? $body : '(Message sans contenu texte)';
    }

    private function authoredAt(ImapMessage $message): CarbonImmutable
    {
        try {
            return CarbonImmutable::instance($message->getDate()->toDate());
        } catch (Throwable) {
            return now()->toImmutable();
        }
    }

    /** @return array<int, array{role: MessageParticipantRole, name: ?string, address: string, position: int}> */
    private function participants(ImapMessage $message): array
    {
        $participants = [];
        foreach (['From' => MessageParticipantRole::From, 'To' => MessageParticipantRole::To, 'Cc' => MessageParticipantRole::Cc, 'ReplyTo' => MessageParticipantRole::ReplyTo] as $method => $role) {
            foreach ($message->{'get'.$method}()->toArray() as $position => $address) {
                if (is_object($address) && filled($address->mail) && filter_var($address->mail, FILTER_VALIDATE_EMAIL)) {
                    $participants[] = ['role' => $role, 'name' => $address->personal ?: null, 'address' => $address->mail, 'position' => $position];
                }
            }
        }

        return $participants;
    }
}
