<?php

namespace App\Filament\Resources\Companies\RelationManagers;

use App\Filament\Resources\People\PersonResource;
use App\Models\Person;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PeopleRelationManager extends RelationManager
{
    protected static bool $isLazy = false;

    protected static string $relationship = 'people';

    protected static ?string $title = 'Contacts';

    protected static string|BackedEnum|null $icon = Heroicon::OutlinedUsers;

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('display_name')
            ->columns([
                TextColumn::make('display_name')
                    ->label('Contact')
                    ->weight('medium')
                    ->searchable(),
                TextColumn::make('pivot.job_title')->label('Fonction')->placeholder('—'),
                IconColumn::make('pivot.is_primary')->label('Principal')->boolean(),
                TextColumn::make('contactMethods.value')
                    ->label('Coordonnées')
                    ->listWithLineBreaks()
                    ->limitList(2),
            ])
            ->recordActions([
                ViewAction::make()
                    ->url(fn (Person $record): string => PersonResource::getUrl('view', ['record' => $record])),
            ])
            ->recordUrl(fn (Person $record): string => PersonResource::getUrl('view', ['record' => $record]));
    }
}
