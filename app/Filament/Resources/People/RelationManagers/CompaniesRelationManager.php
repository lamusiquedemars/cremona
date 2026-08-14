<?php

namespace App\Filament\Resources\People\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CompaniesRelationManager extends RelationManager
{
    protected static bool $isLazy = false;

    protected static string $relationship = 'companies';

    protected static ?string $title = 'Entreprises';

    protected static string|\BackedEnum|null $icon = Heroicon::OutlinedBuildingOffice2;

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Entreprise')
                    ->description(fn ($record): ?string => $record->legal_name)
                    ->weight('medium')
                    ->searchable(),
                TextColumn::make('pivot.job_title')->label('Fonction')->placeholder('—'),
                IconColumn::make('pivot.is_primary')->label('Principale')->boolean(),
                TextColumn::make('industry')->label('Secteur')->placeholder('—'),
                TextColumn::make('website')->label('Site')->url(fn (?string $state): ?string => $state)->openUrlInNewTab(),
            ]);
    }
}
