<?php

namespace App\Filament\Resources\Organizations;

use App\Filament\Resources\Organizations\Pages\CreateOrganization;
use App\Filament\Resources\Organizations\Pages\EditOrganization;
use App\Filament\Resources\Organizations\Pages\ListOrganizations;
use App\Filament\Resources\Organizations\RelationManagers\MembersRelationManager;
use App\Filament\Resources\Organizations\RelationManagers\SitesRelationManager;
use App\Models\Organization;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class OrganizationResource extends Resource
{
    protected static ?string $model = Organization::class;

    protected static bool $isScopedToTenant = false;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;
    protected static string|UnitEnum|null $navigationGroup = 'Plateforme';
    protected static ?string $navigationLabel = 'Organisations';

    public static function shouldRegisterNavigation(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'platform';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nom')->required()->maxLength(255),
            TextInput::make('slug')->label('Identifiant URL')->required()->maxLength(255)->unique(ignoreRecord: true),
            TextInput::make('vertical_pack')->label('Type d’activité')->maxLength(255),
            Select::make('status')->label('Statut')->options(['active' => 'Active', 'inactive' => 'Inactive'])->default('active')->required(),
            Select::make('settings.timezone')
                ->label('Fuseau horaire')
                ->options(array_combine(timezone_identifiers_list(), timezone_identifiers_list()))
                ->default(config('app.timezone', 'UTC'))
                ->searchable()
                ->required()
                ->helperText('Utilisé pour afficher les rendez-vous, synchronisations et résultats de cette organisation.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Organisation')->searchable()->sortable(),
            TextColumn::make('vertical_pack')->label('Activité')->placeholder('—'),
            TextColumn::make('status')->label('Statut')->badge(),
            TextColumn::make('users_count')->label('Membres')->counts('users'),
            TextColumn::make('updated_at')->label('Mis à jour')->since(),
        ])->recordActions([
            Action::make('open_workspace')
                ->label('Ouvrir l’espace')
                ->url(fn (Organization $record): string => '/dashboard/'.$record->slug),
            EditAction::make(),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListOrganizations::route('/'), 'create' => CreateOrganization::route('/create'), 'edit' => EditOrganization::route('/{record}/edit')];
    }

    public static function getRelations(): array
    {
        return [MembersRelationManager::class, SitesRelationManager::class];
    }
}
