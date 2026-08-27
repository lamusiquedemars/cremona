<?php

namespace App\Filament\Resources\Campaigns;

use App\Enums\CampaignStatus;
use App\Filament\Resources\Campaigns\Pages\CreateCampaign;
use App\Filament\Resources\Campaigns\Pages\EditCampaign;
use App\Filament\Resources\Campaigns\Pages\ListCampaigns;
use App\Models\Campaign;
use App\Models\OrganizationIntegration;
use App\Services\GoogleAdsCampaignDraft;
use App\Services\GoogleAdsCampaignPublisher;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class CampaignResource extends Resource
{
    protected static ?string $model = Campaign::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static string|UnitEnum|null $navigationGroup = 'Acquisition';

    protected static ?string $navigationLabel = 'Campagnes';

    protected static ?string $modelLabel = 'campagne';

    protected static ?string $pluralModelLabel = 'campagnes';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Campagne')
                ->description('La clé de suivi doit correspondre exactement au paramètre utm_campaign utilisé dans les liens publicitaires.')
                ->schema([
                    TextInput::make('name')->label('Nom lisible')->required()->maxLength(255),
                    Select::make('channel')->label('Canal')->required()->options([
                        'google_ads' => 'Google Ads',
                        'meta_ads' => 'Meta Ads',
                        'linkedin_ads' => 'LinkedIn Ads',
                        'other' => 'Autre',
                    ]),
                    TextInput::make('tracking_key')
                        ->label('Clé UTM de la campagne')
                        ->helperText('Exemple : criminal-cuiaba. Elle relie les demandes du site à cette campagne.')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('external_reference')->label('Identifiant externe')->maxLength(255),
                    TextInput::make('site_reference')->label('Référence du site')->maxLength(255),
                    Select::make('status')->label('Statut')->options(CampaignStatus::class)->default(CampaignStatus::Draft)->required(),
                    DatePicker::make('starts_on')->label('Début')->native(false),
                    DatePicker::make('ends_on')->label('Fin')->native(false),
                    TextInput::make('planned_budget')->label('Budget prévu')->numeric(),
                    TextInput::make('currency')->label('Devise (ISO)')->placeholder('EUR')->required()->length(3),
                    Textarea::make('notes')->label('Notes')->rows(3)->columnSpanFull(),
                ])->columns(2),
            Section::make('Coûts et résultats par jour')
                ->description('Saisie manuelle provisoire. Google Ads pourra alimenter ces chiffres automatiquement dans une étape suivante.')
                ->schema([
                    Repeater::make('dailyMetrics')
                        ->relationship()
                        ->schema([
                            DatePicker::make('metric_date')->label('Jour')->required()->native(false),
                            TextInput::make('spend')->label('Dépense')->numeric()->default(0)->required(),
                            TextInput::make('impressions')->label('Impressions')->numeric()->default(0)->required(),
                            TextInput::make('clicks')->label('Clics')->numeric()->default(0)->required(),
                            TextInput::make('platform_conversions')->label('Conversions plateforme')->numeric()->default(0)->required(),
                            Select::make('source')->label('Source')->options([
                                'manual' => 'Saisie manuelle',
                                'google_ads' => 'Google Ads',
                                'meta_ads' => 'Meta Ads',
                            ])->default('manual')->required(),
                            TextInput::make('currency')->label('Devise (ISO)')->required()->length(3),
                        ])->columns(4)->defaultItems(0)->addActionLabel('Ajouter une journée'),
                ]),
            Section::make('Préparation Google Ads')
                ->description('Ce brouillon reste dans Cremona. Il servira à créer une campagne Google Search en pause, jamais à la diffuser automatiquement.')
                ->schema([
                    Select::make('configuration.conversion_goal')
                        ->label('Objectif de conversion')
                        ->options(['generate_lead' => 'Demande de contact envoyée'])
                        ->default('generate_lead'),
                    TextInput::make('configuration.final_url')
                        ->label('URL finale')
                        ->url()
                        ->maxLength(2048),
                    TextInput::make('configuration.daily_budget')
                        ->label('Budget quotidien prévu')
                        ->numeric(),
                    TextInput::make('configuration.target_locations')
                        ->label('Zones ciblées')
                        ->helperText('Exemple : France, Paris, Île-de-France.'),
                    TextInput::make('configuration.languages')
                        ->label('Langues')
                        ->helperText('Exemple : fr.'),
                    Repeater::make('configuration.ad_groups')
                        ->label('Groupes d’annonces')
                        ->schema([
                            TextInput::make('name')->label('Nom')->required()->maxLength(255),
                            Textarea::make('keywords')->label('Mots-clés, un par ligne')->rows(4)->required(),
                            Textarea::make('negative_keywords')->label('Exclusions, une par ligne')->rows(3),
                            Textarea::make('headlines')->label('Titres, un par ligne')->rows(4)->required(),
                            Textarea::make('descriptions')->label('Descriptions, une par ligne')->rows(3)->required(),
                        ])->columns(2)->defaultItems(0)->addActionLabel('Ajouter un groupe'),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->label('Campagne')->description(fn (Campaign $record): string => $record->tracking_key)->searchable()->sortable()->weight('medium'),
                TextColumn::make('channel')->label('Canal')->badge()->formatStateUsing(fn (string $state): string => match ($state) {
                    'google_ads' => 'Google Ads', 'meta_ads' => 'Meta Ads', 'linkedin_ads' => 'LinkedIn Ads', default => 'Autre',
                }),
                TextColumn::make('status')->label('Statut')->badge(),
                TextColumn::make('daily_metrics_sum_spend')->label('Dépensé')->money(fn (Campaign $record): string => $record->currency)->sortable(),
                TextColumn::make('attributed_incoming_requests_count')->label('Demandes site')->counts('attributedIncomingRequests')->badge()->color('success'),
                TextColumn::make('starts_on')->label('Début')->date('d/m/Y')->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')->label('Statut')->options(CampaignStatus::class),
                SelectFilter::make('channel')->label('Canal')->options(['google_ads' => 'Google Ads', 'meta_ads' => 'Meta Ads', 'linkedin_ads' => 'LinkedIn Ads', 'other' => 'Autre']),
            ])
            ->recordActions([
                Action::make('preview_google_ads')
                    ->label('Aperçu Google Ads')
                    ->icon(Heroicon::OutlinedEye)
                    ->visible(fn (Campaign $record): bool => $record->channel === 'google_ads')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fermer')
                    ->modalHeading('Aperçu de création Google Ads')
                    ->modalDescription('Cet aperçu ne crée rien dans Google Ads. La campagne sera toujours créée en pause.')
                    ->modalContent(fn (Campaign $record) => view('filament.campaigns.google-ads-preview', [
                        'preview' => app(GoogleAdsCampaignDraft::class)->preview($record),
                    ])),
                Action::make('publish_google_ads_paused')
                    ->label('Créer dans Google Ads en pause')
                    ->icon(Heroicon::OutlinedCloudArrowUp)
                    ->color('warning')
                    ->authorize('update')
                    ->visible(fn (Campaign $record): bool => $record->channel === 'google_ads' && blank($record->external_reference))
                    ->requiresConfirmation()
                    ->modalHeading('Créer la campagne Google Ads en pause ?')
                    ->modalDescription('Cette action crée le budget, la campagne, les groupes, mots-clés et annonces dans Google Ads. Rien ne sera diffusé : la campagne restera en pause jusqu’à une activation séparée.')
                    ->modalSubmitActionLabel('Créer en pause')
                    ->action(function (Campaign $record): void {
                        $integration = OrganizationIntegration::query()
                            ->where('provider', 'google_ads')
                            ->where('name', 'reporting')
                            ->first();

                        if ($integration === null) {
                            Notification::make()->title('Connexion Google Ads à préparer')->body('Renseigne d’abord le compte Google Ads dans l’écran « Google Ads ».')->warning()->send();

                            return;
                        }

                        app(GoogleAdsCampaignPublisher::class)->publishPaused($record, $integration, auth()->user());

                        Notification::make()->title('Campagne créée dans Google Ads')->body('Elle est en pause et ne diffuse aucune annonce.')->success()->send();
                    }),
                Action::make('activate_google_ads')
                    ->label('Activer dans Google Ads')
                    ->icon(Heroicon::OutlinedPlay)
                    ->color('success')
                    ->authorize('update')
                    ->visible(fn (Campaign $record): bool => $record->channel === 'google_ads'
                        && filled($record->external_reference)
                        && $record->status === CampaignStatus::Paused)
                    ->requiresConfirmation()
                    ->modalHeading('Activer la campagne Google Ads ?')
                    ->modalDescription('Cette action rend la campagne diffusible dans Google Ads. Vérifie d’abord le budget, les annonces, les mots-clés et le suivi de conversion.')
                    ->modalSubmitActionLabel('Activer la campagne')
                    ->action(function (Campaign $record): void {
                        $integration = OrganizationIntegration::query()
                            ->where('provider', 'google_ads')
                            ->where('name', 'reporting')
                            ->first();

                        if ($integration === null) {
                            Notification::make()->title('Connexion Google Ads introuvable')->warning()->send();

                            return;
                        }

                        app(GoogleAdsCampaignPublisher::class)->activate($record, $integration, auth()->user());

                        Notification::make()->title('Campagne activée dans Google Ads')->success()->send();
                    }),
                EditAction::make(),
            ])
            ->headerActions([CreateAction::make()->label('Nouvelle campagne')]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withSum('dailyMetrics', 'spend');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCampaigns::route('/'),
            'create' => CreateCampaign::route('/create'),
            'edit' => EditCampaign::route('/{record}/edit'),
        ];
    }
}
