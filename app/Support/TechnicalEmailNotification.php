<?php

namespace App\Support;

use App\Enums\MessageChannel;
use App\Enums\MessageDirection;
use App\Enums\MessageParticipantRole;
use App\Models\ConversationMessage;
use App\Models\EmailMailbox;
use Webklex\PHPIMAP\Message as ImapMessage;

class TechnicalEmailNotification
{
    public static function isSource(EmailMailbox $mailbox, ImapMessage $message, string $folderRole): bool
    {
        $from = $message->getFrom()->toArray()[0] ?? null;

        return $folderRole === 'inbox'
            && is_object($from)
            && self::sameAddress($from->mail ?? null, $mailbox->address)
            && str_starts_with(trim($message->getTextBody()), 'Nouveau message reçu depuis le site.');
    }

    public static function isStored(ConversationMessage $message): bool
    {
        $from = $message->participants->first(fn ($participant) => $participant->role === MessageParticipantRole::From);

        return $message->direction === MessageDirection::Inbound
            && $message->channel === MessageChannel::Email
            && $message->mailbox !== null
            && $from !== null
            && self::sameAddress($from->address, $message->mailbox->address)
            && str_starts_with($message->body_text, 'Nouveau message reçu depuis le site.');
    }

    private static function sameAddress(?string $first, ?string $second): bool
    {
        return filled($first) && filled($second) && mb_strtolower(trim($first)) === mb_strtolower(trim($second));
    }
}
