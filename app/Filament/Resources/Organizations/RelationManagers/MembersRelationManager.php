<?php

namespace App\Filament\Resources\Organizations\RelationManagers;

use App\Enums\OrganizationRole;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MembersRelationManager extends RelationManager
{
    protected static string $relationship = 'users';

    protected static ?string $title = 'Membres autorisés';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nom')->searchable(),
                TextColumn::make('email')->label('Email')->searchable()->copyable(),
                TextColumn::make('pivot.role')->label('Rôle')->badge()->formatStateUsing(fn (OrganizationRole|string $state): string => $state instanceof OrganizationRole ? $state->label() : OrganizationRole::from($state)->label()),
            ])
            ->headerActions([
                AttachAction::make()
                    ->label('Ajouter un membre')
                    ->recordTitleAttribute('email')
                    ->recordSelectSearchColumns(['name', 'email'])
                    ->schema(fn (AttachAction $action): array => [
                        $action->getRecordSelect()->label('Compte'),
                        Select::make('role')->label('Rôle')->options(OrganizationRole::class)->default(OrganizationRole::Collaborator->value)->required(),
                    ]),
            ])
            ->recordActions([DetachAction::make()->label('Retirer')]);
    }
}
