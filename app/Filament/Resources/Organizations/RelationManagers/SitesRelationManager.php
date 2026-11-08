<?php

namespace App\Filament\Resources\Organizations\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SitesRelationManager extends RelationManager
{
    protected static string $relationship = 'sites';

    protected static ?string $title = 'Sites rattachés';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Site')->sortable(),
                TextColumn::make('base_url')->label('Adresse')->url(fn (?string $state): ?string => $state)->sortable(),
                TextColumn::make('status')->label('Statut')->badge()->sortable(),
                TextColumn::make('site_portal_client_id')->label('Identifiant SSO')->placeholder('Non raccordé')->toggleable(),
                TextColumn::make('site_portal_enabled')->label('Portail')->badge()->formatStateUsing(fn (bool $state): string => $state ? 'Actif' : 'Inactif')->color(fn (bool $state): string => $state ? 'success' : 'gray'),
            ])
            ->headerActions([
                CreateAction::make()->label('Rattacher un site')->schema($this->formSchema()),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Configurer')
                    ->modalHeading('Configurer le site')
                    ->modalSubmitActionLabel('Enregistrer')
                    ->modalCancelActionLabel('Annuler')
                    ->schema($this->formSchema()),
                DeleteAction::make()->label('Retirer'),
            ]);
    }

    /** @return array<int, Component> */
    private function formSchema(): array
    {
        return [
            TextInput::make('name')->label('Nom')->required(),
            TextInput::make('base_url')->label('Adresse du site')->url(),
            Select::make('status')->options(['active' => 'Actif', 'inactive' => 'Inactif'])->default('active')->required(),
            Toggle::make('site_portal_enabled')->label('Raccordement SSO actif')->helperText('Active l’authentification du site vers les fonctionnalités Cremona autorisées.')->default(false),
            TextInput::make('site_portal_client_id')->label('Identifiant public du site')->helperText('À reporter dans la configuration du site.')->maxLength(255),
            TextInput::make('site_portal_secret')->label('Secret partagé')->password()->revealable()->dehydrated(fn ($state): bool => filled($state))->helperText('À reporter dans la configuration du site. Il est chiffré au repos dans Cremona.'),
            CheckboxList::make('site_portal_items')->label('Raccourcis autorisés')->options([
                'workspace' => 'Pilotage de l’activité (tableau de bord Cremona)',
                'clients' => 'Clients',
                'requests' => 'Demandes',
                'appointments' => 'Rendez-vous',
                'quotes' => 'Devis',
                'invoices' => 'Facturation',
                'instruments' => 'Instruments',
                'rentals' => 'Locations',
                'workshop_orders' => 'Dossiers atelier',
            ])->columns(2),
            TextInput::make('site_portal_logout_redirect_uri')->label('Retour après déconnexion')->url()->helperText('URL fixe du site, jamais fournie par le navigateur.'),
        ];
    }
}
