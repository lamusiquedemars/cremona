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
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;

class ViewIncomingRequest extends ViewRecord
{
    protected static string $resource = IncomingRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('markRead')
                ->label('Marquer comme lue')
                ->icon(Heroicon::OutlinedEnvelopeOpen)
                ->visible(fn (): bool => $this->canManage() && $this->record->read_at === null)
                ->action(function (IncomingRequestManager $manager): void {
                    Gate::authorize('update', $this->record);
                    $manager->markRead($this->record, auth()->user());
                    $this->reloadRecord();
                    $this->success('Demande marquée comme lue.');
                }),
            Action::make('changeStatus')
                ->label('Changer le statut')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('primary')
                ->visible(fn (): bool => $this->canManage() && $this->record->status->allowedTransitions() !== [])
                ->schema([
                    Select::make('status')
                        ->label('Nouveau statut')
                        ->options(fn (): array => collect($this->record->status->allowedTransitions())
                            ->mapWithKeys(fn (IncomingRequestStatus $status): array => [$status->value => $status->getLabel()])
                            ->all())
                        ->required()
                        ->live(),
                    Select::make('outcome')
                        ->label('Résultat')
                        ->options(IncomingRequestOutcome::class)
                        ->visible(fn (Get $get): bool => $get('status') === IncomingRequestStatus::Closed->value)
                        ->required(fn (Get $get): bool => $get('status') === IncomingRequestStatus::Closed->value),
                    TextInput::make('commercial_value')
                        ->label('Valeur commerciale attribuée')
                        ->numeric()
                        ->minValue(0)
                        ->visible(fn (Get $get): bool => $get('status') === IncomingRequestStatus::Closed->value
                            && $get('outcome') === IncomingRequestOutcome::Converted->value),
                    TextInput::make('commercial_currency')
                        ->label('Devise')
                        ->default('EUR')
                        ->length(3)
                        ->visible(fn (Get $get): bool => $get('status') === IncomingRequestStatus::Closed->value
                            && $get('outcome') === IncomingRequestOutcome::Converted->value)
                        ->required(fn (Get $get): bool => filled($get('commercial_value'))),
                    TextInput::make('lost_reason')
                        ->label('Motif de perte ou de clôture')
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
                    $this->success('Statut mis à jour.');
                }),
            Action::make('assign')
                ->label('Attribuer')
                ->icon(Heroicon::OutlinedUserPlus)
                ->visible(fn (): bool => $this->canManage())
                ->schema([
                    Select::make('user_id')
                        ->label('Responsable')
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
                    $this->success('Responsable attribué.');
                }),
            Action::make('qualifyContact')
                ->label('Créer ou rattacher le contact')
                ->icon(Heroicon::OutlinedUserPlus)
                ->color('success')
                ->visible(fn (): bool => $this->canManage() && $this->record->person_id === null)
                ->modalHeading('Qualifier le contact')
                ->modalDescription(fn (): string => $this->candidateCount() > 0
                    ? $this->candidateCount().' correspondance(s) possible(s) détectée(s). Vérifiez avant de créer une nouvelle fiche.'
                    : 'Aucune correspondance exacte détectée. Vous pouvez rechercher un contact ou créer une nouvelle fiche.')
                ->schema([
                    Radio::make('strategy')
                        ->label('Action')
                        ->options([
                            'existing' => 'Rattacher un contact existant',
                            'create' => 'Créer un nouveau contact',
                        ])
                        ->default(fn (): string => $this->candidateCount() > 0 ? 'existing' : 'create')
                        ->required()
                        ->live(),
                    Select::make('person_id')
                        ->label('Contact existant')
                        ->options(fn (): array => $this->personOptions())
                        ->searchable()
                        ->required(fn (Get $get): bool => $get('strategy') === 'existing')
                        ->visible(fn (Get $get): bool => $get('strategy') === 'existing'),
                    TextInput::make('display_name')
                        ->label('Nom affiché')
                        ->default(fn (): ?string => $this->record->name_snapshot)
                        ->required(fn (Get $get): bool => $get('strategy') === 'create')
                        ->visible(fn (Get $get): bool => $get('strategy') === 'create')
                        ->maxLength(255),
                    TextInput::make('first_name')
                        ->label('Prénom')
                        ->visible(fn (Get $get): bool => $get('strategy') === 'create')
                        ->maxLength(255),
                    TextInput::make('last_name')
                        ->label('Nom')
                        ->visible(fn (Get $get): bool => $get('strategy') === 'create')
                        ->maxLength(255),
                    TextInput::make('email')
                        ->label('E-mail')
                        ->default(fn (): ?string => $this->record->email_snapshot)
                        ->email()
                        ->visible(fn (Get $get): bool => $get('strategy') === 'create')
                        ->maxLength(255),
                    TextInput::make('phone')
                        ->label('Téléphone')
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
                        $message = 'Contact existant rattaché.';
                    } else {
                        $manager->createPersonFromRequest($this->record, $data, auth()->user());
                        $message = 'Contact créé et rattaché.';
                    }

                    $this->reloadRecord();
                    $this->success($message);
                }),
            ActionGroup::make([
                Action::make('linkCompany')
                    ->label('Rattacher une entreprise')
                    ->icon(Heroicon::OutlinedBuildingOffice2)
                    ->schema([
                        Select::make('company_id')
                            ->label('Entreprise')
                            ->options(fn (): array => Company::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (array $data, IncomingRequestManager $manager): void {
                        Gate::authorize('update', $this->record);
                        $manager->linkCompany($this->record, Company::query()->findOrFail($data['company_id']), auth()->user());
                        $this->reloadRecord();
                        $this->success('Entreprise rattachée.');
                    }),
                Action::make('addNote')
                    ->label('Ajouter une note')
                    ->icon(Heroicon::OutlinedChatBubbleBottomCenterText)
                    ->schema([
                        Textarea::make('body')
                            ->label('Note interne')
                            ->rows(5)
                            ->required(),
                    ])
                    ->action(function (array $data, IncomingRequestManager $manager): void {
                        Gate::authorize('update', $this->record);
                        $manager->addNote($this->record, $data['body'], auth()->user());
                        $this->reloadRecord();
                        $this->success('Note ajoutée.');
                    }),
            ])
                ->label('Plus')
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
            'Correspondances possibles' => $suggestions->pluck('display_name', 'id')->all(),
            'Autres contacts' => $others,
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
