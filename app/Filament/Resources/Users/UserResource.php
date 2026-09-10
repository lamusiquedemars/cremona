<?php

namespace App\Filament\Resources\Users;

use App\Enums\OrganizationRole;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static bool $isScopedToTenant = false;

    protected static ?string $navigationLabel = 'Utilisateurs';

    protected static string|\UnitEnum|null $navigationGroup = 'Plateforme';

    public static function shouldRegisterNavigation(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'platform';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nom')->required(),
            TextInput::make('email')->label('Email')->email()->required()->unique(ignoreRecord: true),
            TextInput::make('password')->label('Mot de passe')->password()->required(fn (string $operation) => $operation === 'create')->dehydrated(fn ($state) => filled($state)),
            Toggle::make('is_platform_admin')->label('Super-admin'),
            Section::make('Accès aux organisations')
                ->description('Le profil est réglé séparément pour chaque organisation. Un compte client n’accède jamais à l’administration de la plateforme.')
                ->visibleOn('edit')
                ->schema([
                    Repeater::make('memberships')
                        ->relationship()
                        ->addable(false)
                        ->deletable(false)
                        ->reorderable(false)
                        ->schema([
                            Select::make('organization_id')
                                ->label('Organisation')
                                ->relationship('organization', 'name')
                                ->disabled()
                                ->dehydrated(),
                            Select::make('role')
                                ->label('Profil')
                                ->options(OrganizationRole::options())
                                ->helperText(fn (?string $state): string => OrganizationRole::tryFrom((string) $state)?->description() ?? 'Choisir le profil du compte dans cette organisation.')
                                ->required(),
                        ])
                        ->columns(2),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Nom')->searchable(),
            TextColumn::make('email')->label('Email')->searchable(),
            IconColumn::make('is_platform_admin')->label('Super-admin')->boolean(),
            TextColumn::make('organizations_count')->label('Organisations')->counts('organizations'),
        ])->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
