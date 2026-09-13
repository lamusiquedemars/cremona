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
use Filament\Schemas\Components\Html;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\Size;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class OrganizationResource extends Resource
{
    protected static ?string $model = Organization::class;

    protected static ?string $modelLabel = 'organisation';

    protected static ?string $pluralModelLabel = 'organisations';

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
                    $preset = $registry->preset($state);
                    $enabled = array_flip($registry->withDependencies($preset['modules']));

                    foreach (array_keys($registry->all()) as $module) {
                        $set("modules.{$module}", isset($enabled[$module]));
                    }

                    foreach ($registry->grouped() as $groupKey => $group) {
                        $set("settings.presentation.labels.{$groupKey}", $preset['labels'][$groupKey] ?? null);

                        foreach ($group['modules'] as $definition) {
                            $key = $definition['presentation_key'];
                            $set("settings.presentation.labels.{$key}", $preset['labels'][$key] ?? null);
                        }
                    }
                })
                ->helperText('Le pack applique immédiatement ses modules et ses noms de menu. Les ajustements manuels restent possibles ensuite.'),
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

        $groups = $registry->grouped();

        return array_map(
            function (array $group, string $groupKey, int $index) use ($definitions): Grid {
                $fields = $index === 0 ? [] : [
                    Html::make('<hr class="border-0 border-t border-gray-200 dark:border-white/10">')->columnSpanFull(),
                ];

                $fields = [
                    ...$fields,
                    Text::make(fn (Get $get): string => $get("settings.presentation.labels.{$groupKey}") ?: $group['label'])
                        ->color('primary')
                        ->size(Size::Large)
                        ->weight(FontWeight::Bold)
                        ->columnSpan(4),
                    TextInput::make("settings.presentation.labels.{$groupKey}")
                        ->hiddenLabel()
                        ->placeholder('Nom de la catégorie (facultatif)')
                        ->maxLength(80)
                        ->columnSpan(8),
                ];

                foreach ($group['modules'] as $module => $definition) {
                    $requiredLabels = array_map(
                        fn (string $requiredModule): string => $definitions[$requiredModule]['label'],
                        $definition['requires'],
                    );
                    $label = $definition['label'].(count($requiredLabels) ? ' · dépend de '.implode(', ', $requiredLabels) : '');

                    $fields[] = Text::make(fn (Get $get): string => $get("settings.presentation.labels.{$definition['presentation_key']}") ?: $label)
                        ->tooltip($definition['description'])
                        ->extraAttributes(['class' => 'pl-4'])
                        ->columnSpan(4);
                    $fields[] = TextInput::make("settings.presentation.labels.{$definition['presentation_key']}")
                        ->hiddenLabel()
                        ->placeholder('Nom affiché (facultatif)')
                        ->maxLength(80)
                        ->columnSpan(5);
                    $fields[] = Toggle::make("modules.{$module}")
                        ->hiddenLabel()
                        ->default(false)
                        ->live()
                        ->afterStateUpdated(function (bool $state, Set $set) use ($definition): void {
                            if (! $state) {
                                return;
                            }

                            foreach ($definition['requires'] as $requiredModule) {
                                $set("modules.{$requiredModule}", true);
                            }
                        })
                        ->disabled(fn (Get $get): bool => static::isRequiredByAnEnabledModule($module, $get))
                        ->columnSpan(3);
                }

                return Grid::make(12)
                    ->columns(12)
                    ->schema($fields);
            },
            array_values($groups),
            array_keys($groups),
            array_keys(array_values($groups)),
        );
    }

    private static function isRequiredByAnEnabledModule(string $module, Get $get): bool
    {
        foreach (app(OrganizationModuleRegistry::class)->all() as $candidate => $definition) {
            if (in_array($module, $definition['requires'], true) && $get("modules.{$candidate}") === true) {
                return true;
            }
        }

        return false;
    }
}
