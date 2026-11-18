<?php

namespace App\Filament\RelationManagers;

use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AvailabilityMatchesRelationManager extends RelationManager
{
    protected static string $relationship = 'matches';
    protected static ?string $title = 'Clients à prévenir';
    protected static bool $isLazy = false;

    public function table(Table $table): Table
    {
        return $table->defaultSort('created_at', 'desc')->columns([
            TextColumn::make('search.person.display_name')->label('Client')->searchable(),
            TextColumn::make('search.object_type')->label('Recherche')->formatStateUsing(fn ($state): string => \App\Support\ClientSearchCatalog::label($state) ?? '—'),
            TextColumn::make('match_kind')->label('Rapprochement')->badge()->formatStateUsing(fn (string $state): string => $state === 'match' ? 'Correspond' : 'À vérifier')->color(fn (string $state): string => $state === 'match' ? 'success' : 'warning'),
            TextColumn::make('status')->label('Suivi')->badge()->formatStateUsing(fn (string $state): string => match ($state) {'suggested' => 'À contacter', 'contacted' => 'Contacté', 'dismissed' => 'Écarté', default => $state}),
            TextColumn::make('contacted_at')->label('Contacté le')->dateTime('d/m/Y H:i')->placeholder('—'),
        ])->filters([SelectFilter::make('status')->label('Suivi')->options(['suggested' => 'À contacter', 'contacted' => 'Contacté', 'dismissed' => 'Écarté'])])->recordActions([
            Action::make('contacted')->label('Marquer contacté')->color('success')->visible(fn ($record): bool => $record->status === 'suggested')->action(fn ($record) => $record->update(['status' => 'contacted', 'contacted_at' => now()])),
            Action::make('dismiss')->label('Écarter')->color('gray')->requiresConfirmation()->visible(fn ($record): bool => $record->status === 'suggested')->action(fn ($record) => $record->update(['status' => 'dismissed', 'dismissed_at' => now()])),
        ]);
    }
}
