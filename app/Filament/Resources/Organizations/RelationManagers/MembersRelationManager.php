<?php

namespace App\Filament\Resources\Organizations\RelationManagers;

use App\Enums\OrganizationRole;
use App\Models\User;
use Filament\Actions\Action;
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
                TextColumn::make('pivot.role')->label('Profil')->badge()->formatStateUsing(fn (OrganizationRole|string $state): string => $state instanceof OrganizationRole ? $state->label() : OrganizationRole::from($state)->label()),
            ])
            ->headerActions([
                AttachAction::make()
                    ->label('Ajouter un membre')
                    ->recordTitleAttribute('email')
                    ->recordSelectSearchColumns(['name', 'email'])
                    ->schema(fn (AttachAction $action): array => [
                        $action->getRecordSelect()->label('Compte'),
                        Select::make('role')->label('Profil')->options(OrganizationRole::options())->default(OrganizationRole::Collaborator->value)->required(),
                    ]),
            ])
            ->recordActions([
                Action::make('changeRole')
                    ->label('Modifier le profil')
                    ->icon('heroicon-o-user-circle')
                    ->fillForm(fn (User $record): array => ['role' => $record->pivot->role->value])
                    ->schema([
                        Select::make('role')
                            ->label('Profil')
                            ->options(OrganizationRole::options())
                            ->helperText(fn (?string $state): string => OrganizationRole::tryFrom((string) $state)?->description() ?? 'Choisir le profil du compte.')
                            ->required(),
                    ])
                    ->action(function (User $record, array $data): void {
                        $this->getOwnerRecord()->users()->updateExistingPivot($record, [
                            'role' => $data['role'],
                            'permissions' => null,
                        ]);
                    }),
                DetachAction::make()->label('Retirer'),
            ]);
    }
}
