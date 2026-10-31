<?php

namespace App\Filament\Resources\Conversations\Pages;

use App\Enums\AppointmentModality;
use App\Enums\AppointmentStatus;
use App\Enums\CrmTaskPriority;
use App\Enums\CrmTaskStatus;
use App\Enums\MessageDirection;
use App\Enums\MessageParticipantRole;
use App\Filament\Pages\BusinessViewRecord;
use App\Filament\Resources\Appointments\AppointmentResource;
use App\Filament\Resources\Conversations\ConversationResource;
use App\Filament\Resources\CrmTasks\CrmTaskResource;
use App\Filament\Resources\IncomingRequests\IncomingRequestResource;
use App\Models\Appointment;
use App\Models\Company;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\CrmTask;
use App\Models\IncomingRequest;
use App\Models\Person;
use App\Services\CorrespondenceManager;
use App\Services\IncomingRequestManager;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;

class ViewConversation extends BusinessViewRecord
{
    protected static string $resource = ConversationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('updateContext')
                ->label('Rattacher un client ou une demande')
                ->icon(Heroicon::OutlinedLink)
                ->visible(fn (): bool => Gate::allows('link', $this->record))
                ->modalHeading('Rattacher cette correspondance')
                ->modalDescription('Le message reste inchangé. Ces liens servent uniquement à retrouver son contexte client et son suivi.')
                ->schema([
                    Select::make('person_id')
                        ->label('Contact')
                        ->options(fn (): array => Person::query()->orderBy('display_name')->pluck('display_name', 'id')->all())
                        ->default(fn (): ?int => $this->record->person_id)
                        ->searchable()
                        ->preload(),
                    Select::make('company_id')
                        ->label('Entreprise')
                        ->options(fn (): array => Company::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->default(fn (): ?int => $this->record->company_id)
                        ->searchable()
                        ->preload(),
                    Select::make('incoming_request_id')
                        ->label('Demande')
                        ->options(fn (): array => IncomingRequest::query()
                            ->where(fn ($query) => $query
                                ->whereDoesntHave('conversation')
                                ->orWhereKey($this->record->incoming_request_id))
                            ->orderByDesc('received_at')
                            ->limit(100)
                            ->get()
                            ->mapWithKeys(fn (IncomingRequest $request): array => [$request->id => trim(($request->subject ?: 'Demande sans objet').' — '.($request->name_snapshot ?: $request->email_snapshot ?: ''))])
                            ->all())
                        ->default(fn (): ?int => $this->record->incoming_request_id)
                        ->searchable()
                        ->preload(),
                ])
                ->action(function (array $data, CorrespondenceManager $manager): void {
                    $manager->updateContext($this->record, $data, auth()->user());
                    $this->reloadRecord();
                    Notification::make()->title('Rattachements mis à jour')->success()->send();
                }),
            Action::make('createRequest')
                ->label('Créer une demande de suivi')
                ->icon(Heroicon::OutlinedInboxArrowDown)
                ->visible(fn (): bool => Gate::allows('update', $this->record) && $this->record->incoming_request_id === null)
                ->requiresConfirmation()
                ->modalHeading('Créer une demande à partir de cet email ?')
                ->modalDescription('Le message et le contact seront repris. La correspondance restera liée à cette demande.')
                ->action(function (IncomingRequestManager $manager): void {
                    $request = $manager->createFromConversation($this->record, auth()->user());
                    $this->redirect(IncomingRequestResource::getUrl('view', ['record' => $request]));
                }),
            Action::make('createFollowUpTask')
                ->label('Créer une tâche de suivi')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->visible(fn (): bool => Gate::allows('link', $this->record) && CrmTaskResource::canCreate())
                ->modalHeading('Créer une tâche liée à cette correspondance')
                ->modalDescription('Le contact, l’entreprise et la demande éventuellement liés à cet email seront repris automatiquement.')
                ->schema([
                    TextInput::make('title')->label('Intitulé')->required()->maxLength(255),
                    DateTimePicker::make('due_at')->label('À faire avant le')->seconds(false),
                    Select::make('priority')->label('Priorité')->options(CrmTaskPriority::class)->default(CrmTaskPriority::Normal)->required(),
                    Select::make('assigned_user_id')
                        ->label('Attribuée à')
                        ->options(fn (): array => $this->record->organization->users()->orderBy('name')->pluck('name', 'users.id')->all())
                        ->default(fn (): ?int => auth()->id())
                        ->searchable()
                        ->preload(),
                ])
                ->action(function (array $data): void {
                    CrmTask::query()->create([
                        ...$data,
                        'person_id' => $this->record->person_id,
                        'company_id' => $this->record->company_id,
                        'incoming_request_id' => $this->record->incoming_request_id,
                        'conversation_id' => $this->record->id,
                        'status' => CrmTaskStatus::Open,
                    ]);
                    Notification::make()->title('Tâche de suivi créée')->success()->send();
                }),
            Action::make('scheduleFollowUpAppointment')
                ->label('Planifier un rendez-vous')
                ->icon(Heroicon::OutlinedCalendarDays)
                ->visible(fn (): bool => Gate::allows('link', $this->record) && AppointmentResource::canCreate())
                ->modalHeading('Planifier un rendez-vous lié à cette correspondance')
                ->modalDescription('Le rendez-vous reprendra automatiquement le contexte client de cet email.')
                ->schema([
                    TextInput::make('title')->label('Objet du rendez-vous')->required()->maxLength(255),
                    DateTimePicker::make('starts_at')->label('Début')->required()->seconds(false)->default(fn () => now()->addHour()->startOfHour()),
                    DateTimePicker::make('ends_at')->label('Fin')->required()->seconds(false)->after('starts_at')->default(fn () => now()->addHours(2)->startOfHour()),
                    Select::make('modality')->label('Type')->options(AppointmentModality::class)->default(AppointmentModality::InPerson)->required(),
                    Select::make('assigned_user_id')
                        ->label('Attribué à')
                        ->options(fn (): array => $this->record->organization->users()->orderBy('name')->pluck('name', 'users.id')->all())
                        ->default(fn (): ?int => auth()->id())
                        ->searchable()
                        ->preload(),
                ])
                ->action(function (array $data): void {
                    Appointment::query()->create([
                        ...$data,
                        'person_id' => $this->record->person_id,
                        'company_id' => $this->record->company_id,
                        'incoming_request_id' => $this->record->incoming_request_id,
                        'conversation_id' => $this->record->id,
                        'status' => AppointmentStatus::Scheduled,
                        'timezone' => $this->record->organization->timezone(),
                        'provider' => 'manual',
                    ]);
                    Notification::make()->title('Rendez-vous planifié')->success()->send();
                }),
            Action::make('reply')
                ->label(__('cremona.crm.reply'))
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->color('primary')
                ->visible(fn (): bool => Gate::allows('update', $this->record))
                ->schema([
                    TextInput::make('to')
                        ->label(__('cremona.crm.recipient'))
                        ->email()
                        ->default(fn (): ?string => $this->lastInboundAddress())
                        ->required()
                        ->maxLength(255),
                    TextInput::make('subject')
                        ->label(__('cremona.crm.subject'))
                        ->default(fn (): string => $this->replySubject())
                        ->maxLength(255),
                    Textarea::make('body')->label(__('cremona.crm.message'))->required()->rows(8)->maxLength(10000),
                    ViewField::make('reply_context')
                        ->hiddenLabel()
                        ->view('filament.forms.reply-context', fn (): array => [
                            'message' => $this->previousCorrespondenceMessage(),
                        ]),
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
                            ? __('cremona.crm.reply_accepted')
                            : __('cremona.crm.reply_failed'));

                    $message->transport_status->value === 'accepted'
                        ? $notification->success()
                        : $notification->danger();
                    $notification->send();
                }),
            Action::make('markRead')
                ->label(__('cremona.crm.mark_as_read'))
                ->icon(Heroicon::OutlinedEnvelopeOpen)
                ->visible(fn (): bool => Gate::allows('view', $this->record))
                ->action(function (CorrespondenceManager $manager): void {
                    $manager->markRead($this->record, auth()->user());
                    Notification::make()->title(__('cremona.crm.conversation_marked_as_read'))->success()->send();
                }),
            Action::make('close')
                ->label(__('cremona.crm.close'))
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('gray')
                ->requiresConfirmation()
                ->visible(fn (): bool => Gate::allows('update', $this->record) && $this->record->status->value !== 'closed')
                ->action(function (CorrespondenceManager $manager): void {
                    $manager->closeConversation($this->record, auth()->user());
                    $this->reloadRecord();
                    Notification::make()->title(__('cremona.crm.conversation_closed'))->success()->send();
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

    private function previousCorrespondenceMessage(): ?ConversationMessage
    {
        return $this->record->messages()
            ->whereIn('transport_status', ['received', 'accepted'])
            ->latest('authored_at')
            ->with(['participants', 'author', 'mailbox'])
            ->first();
    }
}
