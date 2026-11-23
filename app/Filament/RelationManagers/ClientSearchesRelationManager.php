<?php

namespace App\Filament\RelationManagers;

use App\Support\ClientSearchCatalog;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ClientSearchesRelationManager extends RelationManager
{
    protected static string $relationship = 'searches';
    protected static ?string $title = 'Recherches';
    protected static bool $isLazy = false;

    public function isReadOnly(): bool { return false; }

    public function table(Table $table): Table
    {
        return $table->defaultSort('created_at', 'desc')->columns([
            TextColumn::make('object_type')->label('Objet')->formatStateUsing(fn (?string $state): ?string => ClientSearchCatalog::label($state))->badge(),
            TextColumn::make('size')->label('Taille')->placeholder('—'),
            TextColumn::make('maximum_budget')->label('Budget max.')->money('EUR')->placeholder('—'),
            TextColumn::make('notes')->label('Précisions')->limit(70)->tooltip(fn ($state): ?string => $state),
            TextColumn::make('status')->label('État')->formatStateUsing(fn (string $state): string => $state === 'open' ? 'Ouverte' : 'Clôturée')->badge(),
        ])->filters([
            SelectFilter::make('status')->label('État')->options(['open' => 'Ouvertes', 'closed' => 'Clôturées']),
        ])->headerActions([
            CreateAction::make()->label('Nouvelle recherche')->schema($this->formFields())->mutateDataUsing(fn (array $data): array => [...$data, 'status' => 'open']),
        ])->recordActions([
            EditAction::make()->label('Modifier')->schema($this->formFields()),
            Action::make('close')->label('Clôturer')->color('gray')->requiresConfirmation()
                ->visible(fn ($record): bool => $record->status === 'open')
                ->action(fn ($record) => $record->update(['status' => 'closed', 'closed_at' => now()])),
            Action::make('reopen')->label('Rouvrir')->color('success')
                ->visible(fn ($record): bool => $record->status === 'closed')
                ->action(fn ($record) => $record->update(['status' => 'open', 'closed_at' => null])),
        ]);
    }

    private function formFields(): array
    {
        return [
            Select::make('object_type')->label('Objet recherché')->options(ClientSearchCatalog::objectTypes())->required()->live(),
            Select::make('size')->label('Taille')->options(fn (Get $get): array => ClientSearchCatalog::sizesFor($get('object_type')))->visible(fn (Get $get): bool => ClientSearchCatalog::usesInstrumentSizes($get('object_type'))),
            TextInput::make('maximum_budget')->label('Budget maximal')->numeric()->prefix('€')->minValue(0),
            Textarea::make('notes')->label('Précisions libres')->rows(3)->maxLength(5000)->helperText('Ex. d’étude, marque souhaitée ou préférence de sonorité.'),
        ];
    }
}
