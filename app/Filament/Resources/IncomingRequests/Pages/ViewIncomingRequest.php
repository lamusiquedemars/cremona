<?php

namespace App\Filament\Resources\IncomingRequests\Pages;

use App\Enums\IncomingRequestOutcome;
use App\Enums\IncomingRequestStatus;
use App\Filament\Resources\IncomingRequests\IncomingRequestResource;
use App\Models\Company;
use App\Models\Person;
use App\Models\User;
use App\Services\ContactMatcher;
use App\Services\IncomingRequestManager;
use App\Tenancy\OrganizationContext;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use App\Filament\Pages\BusinessViewRecord;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;

class ViewIncomingRequest extends BusinessViewRecord
{
    protected static string $resource = IncomingRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('markRead')
                ->label(__('cremona.crm.mark_as_read'))
                ->icon(Heroicon::OutlinedEnvelopeOpen)
                ->visible(fn (): bool => $this->canManage() && $this->record->read_at === null)
                ->action(function (IncomingRequestManager $manager): void {
                    Gate::authorize('update', $this->record);
                    $manager->markRead($this->record, auth()->user());
                    $this->reloadRecord();
                    $this->success(__('cremona.request.marked_as_read'));
                }),
            Action::make('changeStatus')
                ->label(__('cremona.request.change_status'))
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('primary')
                ->visible(fn (): bool => $this->canManage() && $this->record->status->allowedTransitions() !== [])
                ->schema([
                    Select::make('status')
                        ->label(__('cremona.request.new_status'))
                        ->options(fn (): array => collect($this->record->status->allowedTransitions())
                            ->mapWithKeys(fn (IncomingRequestStatus $status): array => [$status->value => $status->getLabel()])
                            ->all())
                        ->required()
                        ->live(),
                    Select::make('outcome')
                        ->label(__('cremona.request.outcome'))
                        ->options(IncomingRequestOutcome::class)
                        ->visible(fn (Get $get): bool => $get('status') === IncomingRequestStatus::Closed->value)
                        ->required(fn (Get $get): bool => $get('status') === IncomingRequestStatus::Closed->value),
                    TextInput::make('commercial_value')
                        ->label(__('cremona.request.commercial_value'))
                        ->numeric()
                        ->minValue(0)
                        ->visible(fn (Get $get): bool => $get('status') === IncomingRequestStatus::Closed->value
                            && $get('outcome') === IncomingRequestOutcome::Converted->value),
                    TextInput::make('commercial_currency')
                        ->label(__('cremona.request.currency'))
                        ->default('EUR')
                        ->length(3)
                        ->visible(fn (Get $get): bool => $get('status') === IncomingRequestStatus::Closed->value
                            && $get('outcome') === IncomingRequestOutcome::Converted->value)
                        ->required(fn (Get $get): bool => filled($get('commercial_value'))),
                    TextInput::make('lost_reason')
                        ->label(__('cremona.request.closing_reason'))
                        ->maxLength(255)
                        ->visible(fn (Get $get): bool => $get('status') === IncomingRequestStatus::Closed->value
                            && filled($get('outcome'))
                            && $get('outcome') !== IncomingRequestOutcome::Converted->value),
                ])
                ->action(function (array $data, IncomingRequestManager $manager): void {
                    Gate::authorize('update', $this->record);
                    $status = IncomingRequestStatus::from($data['status']);
                    $outcome = filled($data['outcome'] ?? null)
                        ? IncomingRequestOutcome::from($data['outcome'])
                        : null;
                    $manager->transition($this->record, $status, $outcome, auth()->user(), [
                        'value' => $data['commercial_value'] ?? null,
                        'currency' => $data['commercial_currency'] ?? null,
                        'lost_reason' => $data['lost_reason'] ?? null,
                    ]);
                    $this->reloadRecord();
                    $this->success(__('cremona.request.status_updated'));
                }),
            Action::make('assign')
                ->label(__('cremona.request.assign'))
                ->icon(Heroicon::OutlinedUserPlus)
                ->visible(fn (): bool => $this->canManage())
                ->schema([
                    Select::make('user_id')
                        ->label(__('cremona.crm.assignee'))
                        ->options(fn (): array => app(OrganizationContext::class)
                            ->require()
                            ->users()
                            ->orderBy('name')
                            ->pluck('name', 'users.id')
                            ->all())
                        ->searchable()
                        ->required(),
                ])
                ->action(function (array $data, IncomingRequestManager $manager): void {
                    Gate::authorize('update', $this->record);
                    $manager->assign($this->record, User::query()->findOrFail($data['user_id']), auth()->user());
                    $this->reloadRecord();
                    $this->success(__('cremona.request.assignee_assigned'));
                }),
            Action::make('qualifyContact')
                ->label(__('cremona.request.create_or_link_contact'))
                ->icon(Heroicon::OutlinedUserPlus)
                ->color('success')
                ->visible(fn (): bool => $this->canManage() && $this->record->person_id === null)
                ->modalHeading(__('cremona.request.qualify_contact'))
                ->modalDescription(fn (): string => $this->candidateCount() > 0
                    ? trans_choice('cremona.request.possible_matches', $this->candidateCount(), ['count' => $this->candidateCount()])
                    : __('cremona.request.no_exact_match'))
                ->schema([
                    Radio::make('strategy')
                        ->label(__('cremona.request.action'))
                        ->options([
                            'existing' => __('cremona.request.link_existing_contact'),
                            'create' => __('cremona.request.create_contact'),
                        ])
                        ->default(fn (): string => $this->candidateCount() > 0 ? 'existing' : 'create')
                        ->required()
                        ->live(),
                    Select::make('person_id')
                        ->label(__('cremona.request.existing_contact'))
                        ->options(fn (): array => $this->personOptions())
                        ->searchable()
                        ->required(fn (Get $get): bool => $get('strategy') === 'existing')
                        ->visible(fn (Get $get): bool => $get('strategy') === 'existing'),
                    TextInput::make('display_name')
                        ->label(__('cremona.request.display_name'))
                        ->default(fn (): ?string => $this->record->name_snapshot)
                        ->required(fn (Get $get): bool => $get('strategy') === 'create')
                        ->visible(fn (Get $get): bool => $get('strategy') === 'create')
                        ->maxLength(255),
                    TextInput::make('first_name')
                        ->label(__('cremona.request.first_name'))
                        ->visible(fn (Get $get): bool => $get('strategy') === 'create')
                        ->maxLength(255),
                    TextInput::make('last_name')
                        ->label(__('cremona.request.last_name'))
                        ->visible(fn (Get $get): bool => $get('strategy') === 'create')
                        ->maxLength(255),
                    TextInput::make('email')
                        ->label(__('cremona.request.email'))
                        ->default(fn (): ?string => $this->record->email_snapshot)
                        ->email()
                        ->visible(fn (Get $get): bool => $get('strategy') === 'create')
                        ->maxLength(255),
                    TextInput::make('phone')
                        ->label(__('cremona.request.phone'))
                        ->default(fn (): ?string => $this->record->phone_snapshot)
                        ->visible(fn (Get $get): bool => $get('strategy') === 'create')
                        ->maxLength(255),
                ])
                ->action(function (array $data, IncomingRequestManager $manager): void {
                    Gate::authorize('update', $this->record);

                    if ($data['strategy'] === 'existing') {
                        $manager->linkPerson(
                            $this->record,
                            Person::query()->findOrFail($data['person_id']),
                            auth()->user(),
                        );
                        $message = __('cremona.request.existing_contact_linked');
                    } else {
                        $manager->createPersonFromRequest($this->record, $data, auth()->user());
                        $message = __('cremona.request.contact_created_and_linked');
                    }

                    $this->reloadRecord();
                    $this->success($message);
                }),
            ActionGroup::make([
                Action::make('linkCompany')
                    ->label(__('cremona.request.link_company'))
                    ->icon(Heroicon::OutlinedBuildingOffice2)
                    ->schema([
                        Select::make('company_id')
                            ->label(__('cremona.request.company'))
                            ->options(fn (): array => Company::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (array $data, IncomingRequestManager $manager): void {
                        Gate::authorize('update', $this->record);
                        $manager->linkCompany($this->record, Company::query()->findOrFail($data['company_id']), auth()->user());
                        $this->reloadRecord();
                        $this->success(__('cremona.request.company_linked'));
                    }),
                Action::make('addNote')
                    ->label(__('cremona.request.add_note'))
                    ->icon(Heroicon::OutlinedChatBubbleBottomCenterText)
                    ->schema([
                        Textarea::make('body')
                            ->label(__('cremona.request.internal_note'))
                            ->rows(5)
                            ->required(),
                    ])
                    ->action(function (array $data, IncomingRequestManager $manager): void {
                        Gate::authorize('update', $this->record);
                        $manager->addNote($this->record, $data['body'], auth()->user());
                        $this->reloadRecord();
                        $this->success(__('cremona.request.note_added'));
                    }),
            ])
                ->label(__('cremona.request.more'))
                ->icon(Heroicon::OutlinedEllipsisHorizontal)
                ->visible(fn (): bool => $this->canManage()),
        ];
    }

    private function canManage(): bool
    {
        return Gate::allows('update', $this->record);
    }

    private function candidateCount(): int
    {
        return app(ContactMatcher::class)
            ->suggestPeople($this->record->email_snapshot, $this->record->phone_snapshot)
            ->count();
    }

    private function personOptions(): array
    {
        $suggestions = app(ContactMatcher::class)
            ->suggestPeople($this->record->email_snapshot, $this->record->phone_snapshot);
        $suggestionIds = $suggestions->modelKeys();
        $others = Person::query()
            ->when($suggestionIds !== [], fn ($query) => $query->whereNotIn('id', $suggestionIds))
            ->orderBy('display_name')
            ->pluck('display_name', 'id')
            ->all();

        return array_filter([
            __('cremona.request.possible_contacts') => $suggestions->pluck('display_name', 'id')->all(),
            __('cremona.request.other_contacts') => $others,
        ]);
    }

    private function reloadRecord(): void
    {
        $this->record->refresh()->load(['activities.actor', 'answers', 'consents', 'person', 'company', 'assignedUser']);
    }

    private function success(string $message): void
    {
        Notification::make()
            ->title($message)
            ->success()
            ->send();
    }
}
