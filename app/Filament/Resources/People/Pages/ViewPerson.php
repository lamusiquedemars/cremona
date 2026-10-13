<?php

namespace App\Filament\Resources\People\Pages;

use App\Enums\CrmTaskPriority;
use App\Enums\CrmTaskStatus;
use App\Filament\Pages\BusinessViewRecord;
use App\Filament\Resources\People\PersonResource;
use App\Models\CrmTask;
use App\Services\CrmRecordManager;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Support\Icons\Heroicon;

class ViewPerson extends BusinessViewRecord
{
    protected static string $resource = PersonResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()->label(__('cremona.person.edit_contact')),
            Action::make('call_follow_up')
                ->label('Préparer un rappel')
                ->icon(Heroicon::OutlinedPhone)
                ->authorize('update')
                ->modalHeading('Suivi d’un appel')
                ->modalDescription('Créez un rappel lié à ce client. Ajoutez une préparation d’instrument seulement si elle est déjà à prévoir.')
                ->form([
                    Textarea::make('request')
                        ->label('Demande du client')
                        ->placeholder('Ex. Changement de taille : rappeler le parent et préparer un alto de 40 cm.')
                        ->rows(4)
                        ->required(),
                    DateTimePicker::make('call_due_at')
                        ->label('Rappeler le client')
                        ->default(now()->addHour())
                        ->seconds(false)
                        ->required(),
                    Toggle::make('create_preparation')
                        ->label('Prévoir aussi la préparation d’un instrument')
                        ->live(),
                    DateTimePicker::make('preparation_due_at')
                        ->label('Préparation à effectuer avant le')
                        ->default(now()->addDay())
                        ->seconds(false)
                        ->visible(fn (callable $get): bool => (bool) $get('create_preparation'))
                        ->required(fn (callable $get): bool => (bool) $get('create_preparation')),
                ])
                ->action(function (array $data): void {
                    $client = $this->record;
                    $request = trim($data['request']);

                    CrmTask::query()->create([
                        'person_id' => $client->getKey(),
                        'title' => 'Rappeler '.$client->display_name,
                        'description' => $request,
                        'status' => CrmTaskStatus::Open,
                        'priority' => CrmTaskPriority::Normal,
                        'due_at' => $data['call_due_at'],
                    ]);

                    if ($data['create_preparation']) {
                        CrmTask::query()->create([
                            'person_id' => $client->getKey(),
                            'title' => 'Préparer un instrument pour '.$client->display_name,
                            'description' => $request,
                            'status' => CrmTaskStatus::Open,
                            'priority' => CrmTaskPriority::Normal,
                            'due_at' => $data['preparation_due_at'],
                        ]);
                    }
                }),
            Action::make('archive')
                ->label(__('cremona.person.archive'))
                ->icon(Heroicon::OutlinedArchiveBox)
                ->color('gray')
                ->authorize('update')
                ->visible(fn (): bool => $this->record->status === 'active')
                ->requiresConfirmation()
                ->modalHeading(__('cremona.person.archive_contact'))
                ->modalDescription(__('cremona.person.archive_description'))
                ->action(fn () => app(CrmRecordManager::class)->archive($this->record)),
            Action::make('reactivate')
                ->label(__('cremona.person.reactivate'))
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('success')
                ->authorize('reactivate')
                ->visible(fn (): bool => $this->record->status === 'archived')
                ->action(fn () => app(CrmRecordManager::class)->reactivate($this->record)),
        ];
    }
}
