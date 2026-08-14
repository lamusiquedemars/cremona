<?php

namespace App\Filament\Resources\IncomingRequests\Pages;

use App\Enums\IncomingRequestOutcome;
use App\Enums\IncomingRequestStatus;
use App\Filament\Resources\IncomingRequests\IncomingRequestResource;
use App\Models\Company;
use App\Models\Person;
use App\Models\User;
use App\Services\IncomingRequestManager;
use App\Tenancy\OrganizationContext;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
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
                ])
                ->action(function (array $data, IncomingRequestManager $manager): void {
                    Gate::authorize('update', $this->record);
                    $status = IncomingRequestStatus::from($data['status']);
                    $outcome = filled($data['outcome'] ?? null)
                        ? IncomingRequestOutcome::from($data['outcome'])
                        : null;
                    $manager->transition($this->record, $status, $outcome, auth()->user());
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
            ActionGroup::make([
                Action::make('linkPerson')
                    ->label('Rattacher un contact')
                    ->icon(Heroicon::OutlinedUser)
                    ->schema([
                        Select::make('person_id')
                            ->label('Contact')
                            ->options(fn (): array => Person::query()->orderBy('display_name')->pluck('display_name', 'id')->all())
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (array $data, IncomingRequestManager $manager): void {
                        Gate::authorize('update', $this->record);
                        $manager->linkPerson($this->record, Person::query()->findOrFail($data['person_id']), auth()->user());
                        $this->reloadRecord();
                        $this->success('Contact rattaché.');
                    }),
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
