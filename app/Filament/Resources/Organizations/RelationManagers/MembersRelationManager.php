<?php

namespace App\Filament\Resources\Organizations\RelationManagers;

use App\Enums\OrganizationPermission;
use App\Enums\OrganizationRole;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MembersRelationManager extends RelationManager
{
    protected static string $relationship = 'users';

    protected static ?string $title = 'Accès des utilisateurs';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Utilisateur')->searchable()->sortable(),
                TextColumn::make('email')->label('Email')->searchable()->copyable()->sortable(),
                TextColumn::make('status')->label('Compte')->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'active' ? 'Actif' : 'Suspendu')
                    ->color(fn (string $state): string => $state === 'active' ? 'success' : 'danger'),
                TextColumn::make('pivot.role')->label('Profil')->badge()
                    ->formatStateUsing(fn (OrganizationRole|string $state): string => $state instanceof OrganizationRole ? $state->label() : OrganizationRole::from($state)->label())->sortable(),
                TextColumn::make('modules')->label('Modules autorisés')
                    ->state(fn (User $record): string => $this->moduleSummary($record))
                    ->wrap(),
                TextColumn::make('capabilities')->label('Autorisations particulières')
                    ->state(fn (User $record): string => $this->capabilitySummary($record))
                    ->wrap(),
            ])
            ->headerActions([
                AttachAction::make()
                    ->label('Ajouter un accès')
                    ->recordTitleAttribute('email')
                    ->recordSelectSearchColumns(['name', 'email'])
                    ->modalHeading('Ajouter un accès à cette organisation')
                    ->modalDescription('Choisissez un compte existant, son profil et, si nécessaire, les seuls modules auxquels il pourra accéder.')
                    ->schema(fn (AttachAction $action): array => [
                        $action->getRecordSelect()->label('Compte'),
                        ...$this->accessFields(),
                    ])
                    ->mutateFormDataUsing(fn (array $data): array => self::normalizeAccessData($data)),
            ])
            ->recordActions([
                EditAction::make('edit_access')
                    ->label('Modifier l’accès')
                    ->modalHeading(fn (User $record): string => 'Accès de '.$record->name)
                    ->modalDescription('Le profil définit le niveau d’action. Vous pouvez restreindre cet accès à certains modules actifs de cette organisation.')
                    ->schema(fn (): array => $this->accessFields())
                    ->mutateRecordDataUsing(function (array $data): array {
                        $data['customize_modules'] = is_array(data_get($data, 'permissions.modules'));

                        return $data;
                    })
                    ->mutateFormDataUsing(fn (array $data): array => self::normalizeAccessData($data)),
                Action::make('open_user')
                    ->label('Ouvrir la fiche utilisateur')
                    ->url(fn (User $record): string => UserResource::getUrl('edit', ['record' => $record])),
                DetachAction::make()->label('Retirer de l’organisation'),
            ]);
    }

    /** @return array<int, Component> */
    private function accessFields(): array
    {
        return [
            Select::make('role')
                ->label('Profil')
                ->options(OrganizationRole::class)
                ->default(OrganizationRole::Collaborator->value)
                ->required()
                ->helperText('Le profil détermine ce que la personne peut faire dans les modules auxquels elle a accès.'),
            Toggle::make('customize_modules')
                ->label('Limiter aux modules cochés')
                ->default(false)
                ->live()
                ->dehydrated(false)
                ->afterStateUpdated(function (bool $state, Set $set): void {
                    if (! $state) {
                        $set('permissions.modules', null);
                    }
                })
                ->helperText('Sinon, la personne accède à tous les modules actuellement actifs de cette organisation.'),
            CheckboxList::make('permissions.modules')
                ->label('Modules autorisés')
                ->options(fn (): array => UserResource::availableModules($this->getOwnerRecord()->getKey()))
                ->columns(2)
                ->bulkToggleable()
                ->visible(fn (Get $get): bool => (bool) $get('customize_modules'))
                ->helperText('Une liste vide retire l’accès à tous les modules, sans supprimer le compte.'),
            CheckboxList::make('permissions.capabilities')
                ->label('Autorisations particulières')
                ->options(OrganizationPermission::delegableOptions())
                ->columns(1)
                ->helperText('Exception ponctuelle au profil : ces accès donnent la main sur des réglages ou des référentiels normalement réservés à Maracuja.'),
        ];
    }

    private function moduleSummary(User $record): string
    {
        $modules = data_get($record->pivot?->permissions, 'modules');

        if (! is_array($modules)) {
            return 'Tous les modules actifs';
        }

        if ($modules === []) {
            return 'Aucun module';
        }

        $available = UserResource::availableModules($this->getOwnerRecord()->getKey());

        return collect($modules)
            ->map(fn (string $module): string => $available[$module] ?? $module)
            ->implode(' · ');
    }

    private function capabilitySummary(User $record): string
    {
        $capabilities = data_get($record->pivot?->permissions, 'capabilities', []);

        if (! is_array($capabilities) || $capabilities === []) {
            return 'Aucune';
        }

        $options = OrganizationPermission::delegableOptions();

        return collect($capabilities)
            ->map(fn (string $capability): string => $options[$capability] ?? $capability)
            ->implode(' · ');
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private static function normalizeAccessData(array $data): array
    {
        if (! ($data['customize_modules'] ?? false)) {
            unset($data['permissions']['modules']);
        }

        unset($data['customize_modules']);

        if (($data['permissions'] ?? null) === []) {
            unset($data['permissions']);
        }

        return $data;
    }
}
