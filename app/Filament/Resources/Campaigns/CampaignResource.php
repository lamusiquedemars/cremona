<?php

namespace App\Filament\Resources\Campaigns;

use App\Enums\CampaignStatus;
use App\Filament\Concerns\UsesOrganizationPresentation;
use App\Filament\Resources\Campaigns\Pages\CreateCampaign;
use App\Filament\Resources\Campaigns\Pages\EditCampaign;
use App\Filament\Resources\Campaigns\Pages\ListCampaigns;
use App\Filament\Resources\Campaigns\Pages\ViewCampaign;
use App\Models\Campaign;
use App\Models\OrganizationIntegration;
use App\Services\GoogleAdsCampaignDraft;
use App\Services\GoogleAdsCampaignPublisher;
use App\Tenancy\OrganizationContext;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use LogicException;
use Throwable;
use UnitEnum;

class CampaignResource extends Resource
{
    use UsesOrganizationPresentation;

    protected static ?string $model = Campaign::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static string|UnitEnum|null $navigationGroup = 'Marketing';

    protected static ?string $navigationLabel = 'Campagnes';

    protected static ?string $presentationKey = 'campaigns';

    protected static string $organizationModule = 'marketing';

    protected static ?string $presentationGroupKey = 'marketing';

    protected static ?string $modelLabel = 'campagne';

    protected static ?string $pluralModelLabel = 'campagnes';

    protected static ?int $navigationSort = 10;

    public static function getOrganizationTimezone(): string
    {
        return app(OrganizationContext::class)->require()->timezone();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
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
                    TextInput::make('currency')->label('Devise (ISO)')->placeholder('EUR')->required()->length(3),
                    Textarea::make('notes')->label('Notes')->rows(3)->columnSpanFull(),
                ])->columns(2),
            Section::make('Coûts et résultats par jour')
                ->description('Les lignes Google Ads sont synchronisées automatiquement. La saisie manuelle reste disponible pour les autres canaux ou les corrections documentées.')
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
                ->columnSpanFull()
                ->schema([
                    Select::make('configuration.conversion_goal')
                        ->label('Objectif de conversion')
                        ->options(['generate_lead' => 'Demande de contact envoyée'])
                        ->default('generate_lead'),
                    TextInput::make('configuration.final_url')
                        ->label('URL finale')
                        ->url()
                        ->maxLength(2048),
                    Select::make('configuration.budget_mode')
                        ->label('Mode de budget')
                        ->options([
                            'daily' => 'Budget quotidien continu',
                            'total' => 'Test borné : budget total et dates fixes',
                        ])
                        ->default('daily')
                        ->live()
                        ->required()
                        ->helperText('Le budget total crée une campagne Google Ads limitée dans le temps ; il ne peut pas être ajouté après création.'),
                    TextInput::make('configuration.daily_budget')
                        ->label('Budget quotidien prévu')
                        ->numeric()
                        ->minValue(0.01)
                        ->visible(fn (Get $get): bool => in_array($get('configuration.budget_mode'), [null, 'daily'], true))
                        ->required(fn (Get $get): bool => in_array($get('configuration.budget_mode'), [null, 'daily'], true)),
                    TextInput::make('planned_budget')
                        ->label('Budget total maximal')
                        ->numeric()
                        ->minValue(0.01)
                        ->visible(fn (Get $get): bool => $get('configuration.budget_mode') === 'total')
                        ->required(fn (Get $get): bool => $get('configuration.budget_mode') === 'total'),
                    DatePicker::make('starts_on')
                        ->label('Début de diffusion')
                        ->native(false)
                        ->visible(fn (Get $get): bool => $get('configuration.budget_mode') === 'total')
                        ->required(fn (Get $get): bool => $get('configuration.budget_mode') === 'total'),
                    DatePicker::make('ends_on')
                        ->label('Fin de diffusion')
                        ->native(false)
                        ->visible(fn (Get $get): bool => $get('configuration.budget_mode') === 'total')
                        ->required(fn (Get $get): bool => $get('configuration.budget_mode') === 'total')
                        ->after('starts_on'),
                    Select::make('configuration.target_country')
                        ->label('Pays ciblé')
                        ->options(['BR' => 'Brésil', 'FR' => 'France'])
                        ->default('BR')
                        ->required(),
                    Textarea::make('configuration.target_locations')
                        ->label('Zones ciblées')
                        ->helperText('Une zone par ligne. Cremona la vérifie auprès de Google Ads dans le pays choisi avant publication.')
                        ->rows(3),
                    Textarea::make('configuration.languages')
                        ->label('Langues')
                        ->helperText('Une langue ISO par ligne, par exemple : pt ou fr.')
                        ->rows(2),
                    Repeater::make('configuration.ad_groups')
                        ->label('Groupes d’annonces')
                        ->columnSpanFull()
                        ->schema([
                            TextInput::make('name')->label('Nom')->required()->maxLength(255)->columnSpanFull(),
                            Grid::make(['default' => 1, 'md' => 2])->schema([
                                Group::make([
                                    Textarea::make('keywords')->label('Mots-clés, un par ligne')->helperText('Sans signe : diffusion large ; “guillemets” : recherche proche de l’expression ; [crochets] : intention très précise. Google peut aussi utiliser des variantes proches.')->rows(4)->required(),
                                    Textarea::make('negative_keywords')->label('Exclusions, une par ligne')->rows(3),
                                ]),
                                Group::make([
                                    Textarea::make('headlines')->label('Titres, un par ligne')->rows(4)->required(),
                                    Textarea::make('descriptions')->label('Descriptions, une par ligne')->rows(3)->required(),
                                ]),
                            ]),
                        ])->columns(1)->defaultItems(0)->addActionLabel('Ajouter un groupe'),
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
                TextColumn::make('google_ads_primary_status')
                    ->label('État Google')
                    ->formatStateUsing(fn (?string $state): string => self::googleAdsPrimaryStatusLabel($state))
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'ELIGIBLE' => 'success',
                        'LEARNING', 'LIMITED' => 'warning',
                        'MISCONFIGURED', 'NOT_ELIGIBLE' => 'danger',
                        default => 'gray',
                    })
                    ->placeholder('À synchroniser')
                    ->toggleable(),
                TextColumn::make('daily_metrics_sum_spend')->label('Dépensé')->money(fn (Campaign $record): string => $record->currency)->sortable(),
                TextColumn::make('attributed_incoming_requests_count')->label('Demandes site')->counts('attributedIncomingRequests')->badge()->color('success'),
                TextColumn::make('google_ads_synced_at')->label('Google actualisé')->since()->placeholder('Jamais')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('starts_on')->label('Début')->date('d/m/Y')->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')->label('Statut')->options(CampaignStatus::class),
                SelectFilter::make('channel')->label('Canal')->options(['google_ads' => 'Google Ads', 'meta_ads' => 'Meta Ads', 'linkedin_ads' => 'LinkedIn Ads', 'other' => 'Autre']),
            ])
            ->recordActions([
                ViewAction::make()->label('Ouvrir le pilotage'),
                Action::make('preview_google_ads')
                    ->label('Prévisualiser la création')
                    ->icon(Heroicon::OutlinedEye)
                    ->visible(fn (Campaign $record): bool => $record->channel === 'google_ads' && blank($record->external_reference))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fermer')
                    ->modalHeading('Prévisualisation avant création Google Ads')
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
                            Notification::make()->title('Connexion Google Ads à préparer')->body('Renseigne d’abord le compte Google Ads dans « Marketing > Publicité ».')->warning()->send();

                            return;
                        }

                        try {
                            app(GoogleAdsCampaignPublisher::class)->publishPaused($record, $integration, auth()->user());
                        } catch (LogicException $exception) {
                            Notification::make()
                                ->title('Création Google Ads arrêtée')
                                ->body($exception->getMessage())
                                ->danger()
                                ->persistent()
                                ->send();

                            return;
                        } catch (Throwable $exception) {
                            report($exception);

                            Notification::make()
                                ->title('Création Google Ads interrompue')
                                ->body('Google Ads a refusé une étape de création. Cremona a annulé les ressources créées pendant cette tentative ; la campagne locale reste en brouillon.')
                                ->danger()
                                ->persistent()
                                ->send();

                            return;
                        }

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
                Action::make('discard_google_ads_paused')
                    ->label('Retirer de Google Ads')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->authorize('update')
                    ->visible(fn (Campaign $record): bool => $record->channel === 'google_ads'
                        && filled($record->external_reference)
                        && $record->status === CampaignStatus::Paused
                        && ! $record->dailyMetrics()->exists())
                    ->requiresConfirmation()
                    ->modalHeading('Retirer cette campagne Google Ads ?')
                    ->modalDescription('La campagne distante en pause sera retirée de Google Ads. Le brouillon, les mots-clés, annonces, budget et dates restent conservés dans Cremona afin de pouvoir la recréer.')
                    ->modalSubmitActionLabel('Retirer de Google Ads')
                    ->action(function (Campaign $record): void {
                        $integration = OrganizationIntegration::query()
                            ->where('provider', 'google_ads')
                            ->where('name', 'reporting')
                            ->first();

                        if ($integration === null) {
                            Notification::make()->title('Connexion Google Ads à préparer')->body('Renseigne d’abord le compte Google Ads dans « Marketing > Publicité ».')->warning()->send();

                            return;
                        }

                        try {
                            app(GoogleAdsCampaignPublisher::class)->discardPaused($record, $integration, auth()->user());
                        } catch (LogicException $exception) {
                            Notification::make()->title('Retrait Google Ads arrêté')->body($exception->getMessage())->danger()->persistent()->send();

                            return;
                        } catch (Throwable $exception) {
                            report($exception);
                            Notification::make()->title('Retrait Google Ads interrompu')->body('Google Ads a refusé le retrait ; la campagne locale n’a pas été modifiée.')->danger()->persistent()->send();

                            return;
                        }

                        Notification::make()->title('Campagne retirée de Google Ads')->body('Le brouillon Cremona est conservé et prêt à être recréé.')->success()->send();
                    }),
                EditAction::make(),
            ])
            ->recordUrl(fn (Campaign $record): string => self::getUrl('view', ['record' => $record]))
            ->headerActions([CreateAction::make()->label('Nouvelle campagne')]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make('Résultats — 30 derniers jours')
                ->description('Données Google Ads observées et demandes réellement reçues par le site.')
                ->columnSpanFull()
                ->columns(4)
                ->schema([
                    TextEntry::make('performance_spend')
                        ->label('Dépensé')
                        ->state(fn (Campaign $record): float => static::performanceSum($record, 'spend'))
                        ->money(fn (Campaign $record): string => $record->currency)
                        ->icon(Heroicon::OutlinedBanknotes)
                        ->color('warning'),
                    TextEntry::make('performance_clicks')
                        ->label('Clics')
                        ->state(fn (Campaign $record): int => (int) static::performanceSum($record, 'clicks'))
                        ->numeric()
                        ->icon(Heroicon::OutlinedCursorArrowRays)
                        ->color('info'),
                    TextEntry::make('performance_conversions')
                        ->label('Conversions Google')
                        ->state(fn (Campaign $record): float => static::performanceSum($record, 'platform_conversions'))
                        ->numeric(decimalPlaces: 2)
                        ->icon(Heroicon::OutlinedArrowTrendingUp)
                        ->color('success'),
                    TextEntry::make('performance_leads')
                        ->label('Demandes du site')
                        ->state(fn (Campaign $record): int => $record->attributedIncomingRequests()->where('received_at', '>=', now()->subDays(30))->count())
                        ->numeric()
                        ->icon(Heroicon::OutlinedInboxArrowDown)
                        ->color('success'),
                    TextEntry::make('performance_impressions')
                        ->label('Impressions')
                        ->state(fn (Campaign $record): int => (int) static::performanceSum($record, 'impressions'))
                        ->numeric()
                        ->icon(Heroicon::OutlinedEye)
                        ->color('gray'),
                    TextEntry::make('performance_ctr')
                        ->label('CTR')
                        ->state(fn (Campaign $record): string => static::rate($record, 'clicks', 'impressions'))
                        ->icon(Heroicon::OutlinedChartBar)
                        ->color('info'),
                    TextEntry::make('performance_cpc')
                        ->label('CPC moyen')
                        ->state(fn (Campaign $record): string => static::costPerClick($record))
                        ->icon(Heroicon::OutlinedCurrencyEuro)
                        ->color('warning'),
                    TextEntry::make('performance_converted_leads')
                        ->label('Demandes converties')
                        ->state(fn (Campaign $record): int => $record->attributedIncomingRequests()->where('received_at', '>=', now()->subDays(30))->whereNotNull('converted_at')->count())
                        ->numeric()
                        ->icon(Heroicon::OutlinedCheckCircle)
                        ->color('success'),
                ]),
            Section::make('Pilotage de la campagne')
                ->description('Les chiffres récents peuvent évoluer avec le délai de conversion de Google Ads.')
                ->columnSpan(2)
                ->schema([
                    TextEntry::make('name')->label('Campagne')->weight('semibold')->size('lg'),
                    TextEntry::make('channel')->label('Canal')->formatStateUsing(fn (string $state): string => $state === 'google_ads' ? 'Google Ads' : $state)->badge(),
                    TextEntry::make('tracking_key')->label('Clé UTM')->copyable(),
                    TextEntry::make('starts_on')->label('Début')->date('d/m/Y')->placeholder('—'),
                    TextEntry::make('ends_on')->label('Fin')->date('d/m/Y')->placeholder('—'),
                    TextEntry::make('planned_budget')->label('Budget prévu')->money(fn (Campaign $record): string => $record->currency)->placeholder('—'),
                ])->columns(3),
            Section::make('État observé dans Google Ads')
                ->columnSpan(1)
                ->schema([
                    TextEntry::make('google_ads_primary_status')
                        ->label('État')
                        ->formatStateUsing(fn (?string $state): string => self::googleAdsPrimaryStatusLabel($state))
                        ->badge()
                        ->color(fn (?string $state): string => match ($state) {
                            'ELIGIBLE' => 'success',
                            'LEARNING', 'LIMITED' => 'warning',
                            'MISCONFIGURED', 'NOT_ELIGIBLE' => 'danger',
                            default => 'gray',
                        })
                        ->placeholder('À synchroniser'),
                    TextEntry::make('google_ads_primary_status_reasons')
                        ->label('Détail')
                        ->formatStateUsing(fn (mixed $state): string => static::googleAdsPrimaryStatusReasonLabel($state)),
                    TextEntry::make('google_ads_synced_at')->label('Dernière observation')->dateTime('d/m/Y H:i')->timezone(fn (): string => static::getOrganizationTimezone())->placeholder('Jamais'),
                    TextEntry::make('google_ads_serving_status')->label('Diffusion')->formatStateUsing(fn (?string $state): string => static::googleAdsServingStatusLabel($state))->placeholder('—'),
                    TextEntry::make('google_ads_bidding_status')->label('Enchères')->formatStateUsing(fn (?string $state): string => static::googleAdsBiddingStatusLabel($state))->placeholder('—'),
                ]),
            Section::make('Configuration observée dans Google Ads')
                ->description('Lecture seule : les écarts sont visibles sans remplacer la préparation enregistrée dans Cremona.')
                ->columnSpanFull()
                ->schema([
                    ViewEntry::make('google_ads_configuration_comparison')
                        ->hiddenLabel()
                        ->state(fn (Campaign $record): array => [
                            'local' => $record->configuration ?? [],
                            'remote' => $record->google_ads_configuration,
                            'synced_at' => $record->google_ads_configuration_synced_at?->setTimezone(static::getOrganizationTimezone())->format('d/m/Y H:i'),
                        ])
                        ->view('filament.campaigns.google-ads-configuration')
                        ->columnSpanFull(),
                ]),
        ]);
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
            'view' => ViewCampaign::route('/{record}'),
            'edit' => EditCampaign::route('/{record}/edit'),
        ];
    }

    private static function googleAdsPrimaryStatusLabel(?string $status): string
    {
        return match ($status) {
            'ELIGIBLE' => 'Éligible',
            'LEARNING' => 'En apprentissage',
            'LIMITED' => 'Limitée',
            'MISCONFIGURED' => 'À corriger',
            'NOT_ELIGIBLE' => 'Non éligible',
            'PAUSED' => 'En pause',
            'PENDING' => 'En attente',
            'ENDED' => 'Terminée',
            'REMOVED' => 'Supprimée',
            default => $status ?? 'À synchroniser',
        };
    }

    private static function googleAdsServingStatusLabel(?string $status): string
    {
        return match ($status) {
            'SERVING' => 'Diffuse',
            'NONE' => 'Ne diffuse pas',
            'ENDED' => 'Terminée',
            'PENDING' => 'En attente',
            'SUSPENDED' => 'Suspendue',
            default => $status ?? '—',
        };
    }

    private static function googleAdsBiddingStatusLabel(?string $status): string
    {
        return match ($status) {
            'LEARNING' => 'En apprentissage',
            'ENABLED' => 'Active',
            'LIMITED' => 'Limitée',
            default => $status ?? '—',
        };
    }

    private static function googleAdsPrimaryStatusReasonLabel(mixed $reasons): string
    {
        $reasons = is_array($reasons) ? $reasons : [$reasons];
        $labels = collect($reasons)->filter()->map(fn (string $reason): string => match ($reason) {
            'BIDDING_STRATEGY_LEARNING' => 'Les enchères automatiques s’ajustent après une modification.',
            'CAMPAIGN_PAUSED' => 'La campagne a été mise en pause.',
            'CAMPAIGN_ENDED' => 'La date de fin est atteinte.',
            'CAMPAIGN_PENDING' => 'La campagne attend sa date de diffusion.',
            default => 'Google Ads signale un point à examiner.',
        })->unique()->values();

        return $labels->isNotEmpty() ? $labels->implode(' ') : '—';
    }

    private static function performanceSum(Campaign $record, string $column): float
    {
        return (float) $record->dailyMetrics()->where('metric_date', '>=', now()->subDays(30)->toDateString())->sum($column);
    }

    private static function rate(Campaign $record, string $numerator, string $denominator): string
    {
        $base = static::performanceSum($record, $denominator);

        return $base > 0 ? number_format((static::performanceSum($record, $numerator) / $base) * 100, 2, ',', ' ').' %' : '—';
    }

    private static function costPerClick(Campaign $record): string
    {
        $clicks = static::performanceSum($record, 'clicks');

        return $clicks > 0
            ? number_format(static::performanceSum($record, 'spend') / $clicks, 2, ',', ' ').' '.$record->currency
            : '—';
    }
}
