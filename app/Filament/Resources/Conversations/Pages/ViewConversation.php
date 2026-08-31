<?php

namespace App\Filament\Resources\Conversations\Pages;

use App\Enums\MessageDirection;
use App\Enums\MessageParticipantRole;
use App\Filament\Resources\Conversations\ConversationResource;
use App\Models\Conversation;
use App\Services\CorrespondenceManager;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;

class ViewConversation extends ViewRecord
{
    protected static string $resource = ConversationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reply')
                ->label('Répondre')
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->color('primary')
                ->visible(fn (): bool => Gate::allows('update', $this->record))
                ->schema([
                    TextInput::make('to')
                        ->label('Destinataire')
                        ->email()
                        ->default(fn (): ?string => $this->lastInboundAddress())
                        ->helperText('Adresse préremplie depuis le dernier message reçu ; modifiable si nécessaire.')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('subject')
                        ->label('Objet')
                        ->default(fn (): string => $this->replySubject())
                        ->maxLength(255),
                    Textarea::make('body')->label('Message')->required()->rows(8)->maxLength(10000),
                ])
                ->action(function (array $data, CorrespondenceManager $manager): void {
                    /** @var Conversation $conversation */
                    $conversation = $this->record;
                    $draft = $manager->createDraftReply($conversation, $data['body'], [[
                        'role' => MessageParticipantRole::To,
                        'address' => $data['to'],
                    ]], auth()->user(), $data['subject'] ?? null);
                    $message = $manager->sendDraft($draft, auth()->user());
                    $this->reloadRecord();
                    $notification = Notification::make()
                        ->title($message->transport_status->value === 'accepted'
                            ? 'Réponse acceptée par le serveur SMTP.'
                            : 'La réponse n’a pas pu être envoyée.');

                    $message->transport_status->value === 'accepted'
                        ? $notification->success()
                        : $notification->danger();
                    $notification->send();
                }),
            Action::make('markRead')
                ->label('Marquer comme lue')
                ->icon(Heroicon::OutlinedEnvelopeOpen)
                ->visible(fn (): bool => Gate::allows('view', $this->record))
                ->action(function (CorrespondenceManager $manager): void {
                    $manager->markRead($this->record, auth()->user());
                    Notification::make()->title('Conversation marquée comme lue.')->success()->send();
                }),
            Action::make('close')
                ->label('Clôturer')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('gray')
                ->requiresConfirmation()
                ->visible(fn (): bool => Gate::allows('update', $this->record) && $this->record->status->value !== 'closed')
                ->action(function (CorrespondenceManager $manager): void {
                    $manager->closeConversation($this->record, auth()->user());
                    $this->reloadRecord();
                    Notification::make()->title('Conversation clôturée.')->success()->send();
                }),
        ];
    }

    private function reloadRecord(): void
    {
        $this->record->refresh()->load([
            'person', 'company', 'incomingRequest', 'assignedUser', 'messages' => fn ($query) => $query->orderBy('authored_at'),
        ]);
    }

    private function lastInboundAddress(): ?string
    {
        $message = $this->record->messages()
            ->where('direction', MessageDirection::Inbound)
            ->latest('authored_at')
            ->first();

        return $message?->participants()
            ->where('role', MessageParticipantRole::From)
            ->orderBy('position')
            ->value('address');
    }

    private function replySubject(): string
    {
        $subject = trim((string) $this->record->subject);

        return preg_match('/^(?:re|r[eé]p)\s*:/ui', $subject) === 1
            ? $subject
            : 'Re: '.$subject;
    }
}
