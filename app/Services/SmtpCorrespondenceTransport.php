<?php

namespace App\Services;

use App\Contracts\CorrespondenceTransport;
use App\Enums\MessageParticipantRole;
use App\Models\ConversationMessage;
use App\Models\EmailMailbox;
use App\Support\EmailReplyComposer;
use LogicException;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Throwable;

class SmtpCorrespondenceTransport implements CorrespondenceTransport
{
    public function __construct(private readonly EmailReplyComposer $replyComposer) {}

    public function send(ConversationMessage $message): string
    {
        $mailbox = $message->mailbox ?? EmailMailbox::query()->where('status', 'active')->first();
        if ($mailbox === null || $mailbox->status !== 'active') {
            throw new LogicException('Aucune boîte email active n’est disponible pour cette réponse.');
        }

        $credentials = $mailbox->integration->credentials;
        foreach (['smtp_host', 'smtp_port', 'smtp_username', 'smtp_password'] as $key) {
            if (blank($credentials[$key] ?? null)) {
                throw new LogicException('La configuration SMTP de cette boîte est incomplète.');
            }
        }

        $content = $this->replyContent($message);
        $email = (new Email)
            ->from(new Address($mailbox->address, $mailbox->display_name ?: $mailbox->address))
            ->subject($message->subject ?? '')
            ->text($content['text'])
            ->html($content['html']);
        $this->addRecipients($email, $message);

        $inReplyTo = ConversationMessage::canonicalHeaderId($message->in_reply_to);
        if ($inReplyTo !== null) {
            $email->getHeaders()->addIdHeader('In-Reply-To', $inReplyTo);
        }
        $references = $message->references()
            ->orderBy('position')
            ->pluck('reference')
            ->map(fn (string $reference): ?string => ConversationMessage::canonicalHeaderId($reference))
            ->filter()
            ->all();
        if ($references !== []) {
            $email->getHeaders()->addIdHeader('References', $references);
        }

        $transport = new EsmtpTransport(
            $credentials['smtp_host'],
            (int) $credentials['smtp_port'],
            (int) $credentials['smtp_port'] === 465,
        );
        $transport->setUsername($credentials['smtp_username']);
        $transport->setPassword($credentials['smtp_password']);

        try {
            return $transport->send($email)->getMessageId();
        } finally {
            try {
                $transport->stop();
            } catch (Throwable) {
                // The SMTP server may already have closed the connection after accepting the message.
            }
        }
    }

    /** @return array{text: string, html: string} */
    private function replyContent(ConversationMessage $message): array
    {
        $previous = $message->in_reply_to === null ? null : ConversationMessage::query()
            ->where('message_id_hash', ConversationMessage::headerHash(ConversationMessage::canonicalHeaderId($message->in_reply_to)))
            ->with('participants')
            ->first();
        $author = $previous?->participants->firstWhere('role', MessageParticipantRole::From);

        return $this->replyComposer->compose(
            $message->body_text,
            $previous?->body_text,
            $author?->name ?: $author?->address,
            $previous?->authored_at,
            $message->organization->timezone(),
        );
    }

    private function addRecipients(Email $email, ConversationMessage $message): void
    {
        $recipients = 0;
        foreach ($message->participants as $participant) {
            $address = new Address($participant->address, $participant->name ?: '');
            match ($participant->role) {
                MessageParticipantRole::To => $email->addTo($address),
                MessageParticipantRole::Cc => $email->addCc($address),
                MessageParticipantRole::Bcc => $email->addBcc($address),
                default => null,
            };
            if (in_array($participant->role, [MessageParticipantRole::To, MessageParticipantRole::Cc, MessageParticipantRole::Bcc], true)) {
                $recipients++;
            }
        }

        if ($recipients === 0) {
            throw new LogicException('La réponse ne possède aucun destinataire email.');
        }
    }
}
