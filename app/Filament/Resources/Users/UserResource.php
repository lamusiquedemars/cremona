<?php

namespace App\Filament\Resources\Users;

use App\Enums\OrganizationRole;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Services\OrganizationModuleRegistry;
use App\Services\UserPasswordSetupLink;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Arr;

class UserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static ?string $modelLabel = 'utilisateur';
    protected static ?string $pluralModelLabel = 'utilisateurs';
    protected static bool $isScopedToTenant = false;
    protected static ?string $navigationLabel = 'Utilisateurs et accès';
    protected static string|\UnitEnum|null $navigationGroup = 'Plateforme';
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user() instanceof User && auth()->user()->is_platform_admin;
    }

    public static function canAccess(): bool
    {
        return auth()->user() instanceof User && auth()->user()->is_platform_admin;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Compte')
                ->description('Identité, état du compte et accès à la plateforme Cremona.')
                ->columns(2)
                ->schema([
                    TextInput::make('name')->label('Nom')->required()->maxLength(255),
                    TextInput::make('email')->label('E-mail')->email()->required()->unique(ignoreRecord: true)->maxLength(255),
                    Select::make('status')->label('État du compte')->options(['active' => 'Actif', 'suspended' => 'Suspendu'])
                        ->default('active')->required()
                        ->helperText('Un compte suspendu ne peut plus se connecter, sans perdre ses accès ni son historique.'),
                    Toggle::make('is_platform_admin')->label('Administrateur de la plateforme')
                        ->helperText('Réservé à l’équipe qui administre toutes les organisations. Ce n’est pas un rôle client.'),
                    TextInput::make('password')->label('Mot de passe')->password()->minLength(12)
                        ->dehydrated(fn ($state): bool => filled($state))
                        ->helperText('Laissez vide : la personne choisira elle-même son mot de passe à partir du lien envoyé.'),
                    Toggle::make('send_password_setup_link')->label('Envoyer un lien de création du mot de passe')
                        ->default(true)->visibleOn('create')->dehydrated(false)
                        ->helperText('Un e-mail sécurisé est envoyé après la création du compte.'),
                    Placeholder::make('last_login_at')->label('Dernière connexion')
                        ->content(fn (?User $record): string => $record?->last_login_at?->timezone(config('app.timezone'))->translatedFormat('d F Y à H:i') ?? 'Jamais connectée')
                        ->visibleOn('edit'),
                ]),
            Section::make('Accès par organisation')
                ->description('Le profil et les modules sont définis pour chaque organisation : une même personne peut donc avoir des accès différents selon le client.')
                ->schema([
                    Repeater::make('memberships')->relationship()->label('Organisations accessibles')
                        ->addActionLabel('Ajouter un accès à une organisation')->defaultItems(0)->columns(2)
                        ->mutateRelationshipDataBeforeFillUsing(function (array $data): array {
                            if (($data['role'] ?? null) instanceof OrganizationRole) {
                                $data['role'] = $data['role']->value;
                            }

                            return $data;
                        })
                        ->schema([
                            Select::make('organization_id')->label('Organisation')->relationship('organization', 'name')
                                ->searchable()->preload()->required()->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                            Select::make('role')->label('Profil')->options(OrganizationRole::class)
                                ->default(OrganizationRole::Collaborator->value)->required()->live()
                                ->helperText(function (Get $get): string {
                                    $role = $get('role');

                                    return self::roleHelp($role instanceof OrganizationRole ? $role->value : (string) $role);
                                }),
                            Toggle::make('customize_modules')->label('Limiter aux modules cochés')
                                ->default(false)->live()->dehydrated(false)
                                ->afterStateHydrated(function (Toggle $component, ?OrganizationMembership $record): void {
                                    $component->state(is_array(data_get($record?->permissions, 'modules')));
                                })
                                ->helperText('Sinon, ce profil accède à tous les modules actuellement actifs de cette organisation.'),
                            CheckboxList::make('permissions.modules')->label('Modules autorisés')
                                ->options(fn (Get $get): array => self::availableModules($get('organization_id')))
                                ->columns(2)->bulkToggleable()
                                ->visible(fn (Get $get): bool => (bool) $get('customize_modules'))
                                ->helperText('Cette liste remplace le profil pour les modules : une liste vide retire tous les modules.'),
                        ])
                        ->itemLabel(function (array $state): ?string {
                            $organizationId = $state['organization_id'] ?? null;
                            return $organizationId ? Organization::query()->find($organizationId)?->name : 'Nouvel accès';
                        })->collapsible(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Nom')->searchable()->sortable(),
            TextColumn::make('email')->label('E-mail')->searchable()->copyable(),
            TextColumn::make('status')->label('État')->badge()->formatStateUsing(fn (string $state): string => $state === 'active' ? 'Actif' : 'Suspendu')
                ->color(fn (string $state): string => $state === 'active' ? 'success' : 'danger'),
            TextColumn::make('memberships_count')->label('Organisations')->counts('memberships'),
            TextColumn::make('last_login_at')->label('Dernière connexion')->since()->placeholder('Jamais'),
            IconColumn::make('is_platform_admin')->label('Plateforme')->boolean()->tooltip('Administrateur de la plateforme'),
        ])->recordActions([
            Action::make('send_password_setup_link')->label('Envoyer le lien d’accès')->icon(Heroicon::OutlinedPaperAirplane)
                ->visible(fn (User $record): bool => $record->isActive())->requiresConfirmation()
                ->modalDescription('La personne recevra un e-mail pour choisir ou renouveler son mot de passe.')
                ->action(function (User $record): void {
                    app(UserPasswordSetupLink::class)->send($record);
                    Notification::make()->success()->title('Lien d’accès envoyé')->send();
                }),
            EditAction::make(),
            DeleteAction::make()->visible(fn (User $record): bool => $record->getKey() !== auth()->id()),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListUsers::route('/'), 'create' => CreateUser::route('/create'), 'edit' => EditUser::route('/{record}/edit')];
    }

    /** @return array<string, string> */
    public static function availableModules(int|string|null $organizationId): array
    {
        if (! $organizationId || ! ($organization = Organization::query()->find($organizationId))) return [];
        $registry = app(OrganizationModuleRegistry::class);
        $definitions = $registry->all();
        return collect($registry->enabledFor($organization))
            ->mapWithKeys(fn (string $module): array => [$module => $definitions[$module]['label'] ?? $module])->all();
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public static function normalizeMembershipData(array $data): array
    {
        foreach ($data['memberships'] ?? [] as $key => $membership) {
            if (! ($membership['customize_modules'] ?? false)) Arr::forget($membership, 'permissions.modules');
            unset($membership['customize_modules']);
            $data['memberships'][$key] = $membership;
        }
        return $data;
    }

    private static function roleHelp(string $role): string
    {
        return match ($role) {
            OrganizationRole::Owner->value => 'Propriétaire : accès complet à cette organisation et à ses réglages.',
            OrganizationRole::Administrator->value => 'Administrateur : gestion de l’équipe, des réglages et du travail quotidien.',
            OrganizationRole::Collaborator->value => 'Collaborateur : travaille dans les modules autorisés, sans gérer l’organisation.',
            default => 'Lecteur : consulte les modules autorisés sans modifier les données.',
        };
    }
}
