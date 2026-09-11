<?php

namespace App\Filament\Resources\Organizations;

use App\Filament\Resources\Organizations\Pages\CreateOrganization;
use App\Filament\Resources\Organizations\Pages\EditOrganization;
use App\Filament\Resources\Organizations\Pages\ListOrganizations;
use App\Filament\Resources\Organizations\RelationManagers\MembersRelationManager;
use App\Filament\Resources\Organizations\RelationManagers\SitesRelationManager;
use App\Models\Organization;
use App\Services\OrganizationModuleRegistry;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
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
            Select::make('vertical_pack')
                ->label('Pack métier')
                ->options([
                    '__none__' => 'Aucun pack métier',
                    'luthier' => 'Luthier — instruments, atelier, location et stock',
                ])
                ->default('__none__')
                ->live()
                ->afterStateUpdated(function (?string $state, Get $get, Set $set): void {
                    $registry = app(OrganizationModuleRegistry::class);
                    $selected = $registry->selectedFromSelection((array) $get('modules'));
                    $enabled = array_flip($registry->forPack($state === 'luthier' ? 'luthier' : null, $selected));

                    foreach (array_keys($registry->all()) as $module) {
                        $set("modules.{$module}", isset($enabled[$module]));
                    }
                })
                ->helperText('Le pack Luthier active son socle de travail : suivi client, devis, atelier, locations, stock et connecteurs. Aucun pack retire les modules propres au métier Luthier, sans supprimer leurs données.'),
            Select::make('status')->label('Statut')->options(['active' => 'Active', 'inactive' => 'Inactive'])->default('active')->required(),
            Select::make('settings.timezone')
                ->label('Fuseau horaire')
                ->options(array_combine(timezone_identifiers_list(), timezone_identifiers_list()))
                ->default(config('app.timezone', 'UTC'))
                ->searchable()
                ->required()
                ->helperText('Utilisé pour afficher les rendez-vous, synchronisations et résultats de cette organisation.'),
            Section::make('Modules et présentation')
                ->description('Chaque module est activé et nommé ici. Une capacité inactive est absente du menu, du tableau de bord et des accès directs ; ses données restent conservées.')
                ->columnSpanFull()
                ->schema(static::moduleGroups()),
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

    /** @return array<int, Grid> */
    private static function moduleGroups(): array
    {
        $registry = app(OrganizationModuleRegistry::class);
        $definitions = $registry->all();

        return array_map(
            function (array $group, string $groupKey) use ($definitions): Grid {
                $singleModule = count($group['modules']) === 1;
                $fields = $singleModule ? [] : [
                    Text::make($group['label'])->columnSpan(4),
                    TextInput::make("settings.presentation.labels.{$groupKey}")
                        ->hiddenLabel()
                        ->placeholder('Nom de cette rubrique dans le menu')
                        ->maxLength(80)
                        ->columnSpan(8),
                ];

                foreach ($group['modules'] as $module => $definition) {
                    $requiredLabels = array_map(
                        fn (string $requiredModule): string => $definitions[$requiredModule]['label'],
                        $definition['requires'],
                    );
                    $label = $definition['label'].(count($requiredLabels) ? ' · dépend de '.implode(', ', $requiredLabels) : '');

                    $fields[] = Text::make($singleModule ? $definition['label'] : $label)
                        ->tooltip($definition['description'])
                        ->columnSpan(4);
                    $fields[] = TextInput::make("settings.presentation.labels.{$definition['presentation_key']}")
                        ->hiddenLabel()
                        ->placeholder($singleModule ? 'Nom affiché dans le menu (facultatif)' : 'Nom affiché (facultatif)')
                        ->maxLength(80)
                        ->columnSpan(5);
                    $fields[] = Toggle::make("modules.{$module}")
                        ->hiddenLabel()
                        ->default(false)
                        ->columnSpan(3);
                }

                return Grid::make(12)
                    ->columns(12)
                    ->schema($fields);
            },
            $registry->grouped(),
            array_keys($registry->grouped()),
        );
    }
}
