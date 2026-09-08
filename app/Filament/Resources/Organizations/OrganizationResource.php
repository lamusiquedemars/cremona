<?php

namespace App\Filament\Resources\Organizations;

use App\Filament\Resources\Organizations\Pages\CreateOrganization;
use App\Filament\Resources\Organizations\Pages\EditOrganization;
use App\Filament\Resources\Organizations\Pages\ListOrganizations;
use App\Filament\Resources\Organizations\RelationManagers\MembersRelationManager;
use App\Filament\Resources\Organizations\RelationManagers\SitesRelationManager;
use App\Models\Organization;
use App\Services\OrganizationPresentation;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
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
            Section::make('Présentation métier')
                ->description('Ces libellés adaptent l’interface sans modifier les données, les droits ni les intégrations.')
                ->columnSpanFull()
                ->schema(static::presentationGroups()),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Organisation')->searchable()->sortable()
                ->url(fn (Organization $record): string => '/dashboard/'.$record->slug),
            TextColumn::make('vertical_pack')->label('Activité')->placeholder('—'),
            TextColumn::make('status')->label('Statut')->badge(),
            TextColumn::make('users_count')->label('Membres')->counts('users'),
            TextColumn::make('updated_at')->label('Mis à jour')->since(),
        ])->recordActions([EditAction::make()->label('Modifier')]);
    }

    public static function getPages(): array
    {
        return ['index' => ListOrganizations::route('/'), 'create' => CreateOrganization::route('/create'), 'edit' => EditOrganization::route('/{record}/edit')];
    }

    public static function getRelations(): array
    {
        return [MembersRelationManager::class, SitesRelationManager::class];
    }

    /** @return array<int, Section> */
    private static function presentationGroups(): array
    {
        return array_map(
            function (array $group, string $groupKey): Section {
                $fields = [
                    TextInput::make("settings.presentation.labels.{$groupKey}")
                        ->label('Nom du groupe dans le menu')
                        ->placeholder($group['label'])
                        ->maxLength(80)
                        ->inlineLabel()
                        ->columnSpanFull(),
                ];

                foreach ($group['items'] as $key => $label) {
                    $fields[] = TextInput::make("settings.presentation.labels.{$key}")
                        ->label($label)
                        ->maxLength(80)
                        ->inlineLabel()
                        ->columnSpan(9);
                    $fields[] = Toggle::make("settings.presentation.visible.{$key}")
                        ->label('Visible dans le menu')
                        ->default(true)
                        ->inlineLabel()
                        ->columnSpan(3);
                }

                return Section::make($group['label'])
                    ->compact()
                    ->columns(12)
                    ->schema($fields);
            },
            OrganizationPresentation::groups(),
            array_keys(OrganizationPresentation::groups()),
        );
    }
}
