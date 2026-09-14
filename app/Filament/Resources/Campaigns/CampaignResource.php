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
            Section::make(__('cremona.campaign.campaign'))
                ->description(__('cremona.campaign.tracking_key_description'))
                ->schema([
                    TextInput::make('name')->label(__('cremona.campaign.display_name'))->required()->maxLength(255),
                    Select::make('channel')->label(__('cremona.campaign.channel'))->required()->options([
                        'google_ads' => 'Google Ads',
                        'meta_ads' => 'Meta Ads',
                        'linkedin_ads' => 'LinkedIn Ads',
                        'other' => 'Autre',
                    ]),
                    TextInput::make('tracking_key')
                        ->label(__('cremona.campaign.utm_key'))
                        ->helperText(__('cremona.campaign.utm_key_help'))
                        ->required()
                        ->maxLength(255),
                    TextInput::make('external_reference')->label(__('cremona.campaign.external_reference'))->maxLength(255),
                    TextInput::make('site_reference')->label(__('cremona.campaign.site_reference'))->maxLength(255),
                    Select::make('status')->label(__('cremona.crm.status'))->options(CampaignStatus::class)->default(CampaignStatus::Draft)->required(),
                    TextInput::make('currency')->label(__('cremona.campaign.currency'))->placeholder('EUR')->required()->length(3),
                    Textarea::make('notes')->label(__('cremona.campaign.notes'))->rows(3)->columnSpanFull(),
                ])->columns(2),
            Section::make(__('cremona.campaign.daily_results'))
                ->description(__('cremona.campaign.daily_results_description'))
                ->schema([
                    Repeater::make('dailyMetrics')
                        ->relationship()
                        ->schema([
                            DatePicker::make('metric_date')->label(__('cremona.campaign.day'))->required()->native(false),
                            TextInput::make('spend')->label(__('cremona.dashboard.recorded_spend'))->numeric()->default(0)->required(),
                            TextInput::make('impressions')->label(__('cremona.dashboard.impressions'))->numeric()->default(0)->required(),
                            TextInput::make('clicks')->label(__('cremona.dashboard.clicks'))->numeric()->default(0)->required(),
                            TextInput::make('platform_conversions')->label(__('cremona.campaign.platform_conversions'))->numeric()->default(0)->required(),
                            Select::make('source')->label(__('cremona.campaign.source'))->options([
                                'manual' => __('cremona.campaign.manual_entry'),
                                'google_ads' => 'Google Ads',
                                'meta_ads' => 'Meta Ads',
                            ])->default('manual')->required(),
                            TextInput::make('currency')->label(__('cremona.campaign.currency'))->required()->length(3),
                        ])->columns(4)->defaultItems(0)->addActionLabel(__('cremona.campaign.add_day')),
                ]),
            Section::make(__('cremona.campaign.google_ads_preparation'))
                ->description(__('cremona.campaign.google_ads_preparation_description'))
                ->columnSpanFull()
                ->schema([
                    Select::make('configuration.conversion_goal')
                        ->label(__('cremona.campaign.conversion_goal'))
                        ->options(['generate_lead' => __('cremona.campaign.contact_request_sent')])
                        ->default('generate_lead'),
                    TextInput::make('configuration.final_url')
                        ->label(__('cremona.campaign.final_url'))
                        ->url()
                        ->maxLength(2048),
                    Select::make('configuration.budget_mode')
                        ->label(__('cremona.campaign.budget_mode'))
                        ->options([
                            'daily' => __('cremona.campaign.daily_budget_mode'),
                            'total' => __('cremona.campaign.total_budget_mode'),
                        ])
                        ->default('daily')
                        ->live()
                        ->required()
                        ->helperText(__('cremona.campaign.budget_mode_help')),
                    TextInput::make('configuration.daily_budget')
                        ->label(__('common.daily_budget'))
                        ->numeric()
                        ->minValue(0.01)
                        ->visible(fn (Get $get): bool => in_array($get('configuration.budget_mode'), [null, 'daily'], true))
                        ->required(fn (Get $get): bool => in_array($get('configuration.budget_mode'), [null, 'daily'], true)),
                    TextInput::make('planned_budget')
                        ->label(__('common.maximum_total_budget'))
                        ->numeric()
                        ->minValue(0.01)
                        ->visible(fn (Get $get): bool => $get('configuration.budget_mode') === 'total')
                        ->required(fn (Get $get): bool => $get('configuration.budget_mode') === 'total'),
                    DatePicker::make('starts_on')
                        ->label(__('cremona.campaign.start_date'))
                        ->native(false)
                        ->visible(fn (Get $get): bool => $get('configuration.budget_mode') === 'total')
                        ->required(fn (Get $get): bool => $get('configuration.budget_mode') === 'total'),
                    DatePicker::make('ends_on')
                        ->label(__('cremona.campaign.end_date'))
                        ->native(false)
                        ->visible(fn (Get $get): bool => $get('configuration.budget_mode') === 'total')
                        ->required(fn (Get $get): bool => $get('configuration.budget_mode') === 'total')
                        ->after('starts_on'),
                    Select::make('configuration.target_country')
                        ->label(__('cremona.campaign.target_country'))
                        ->options(['BR' => __('cremona.campaign.brazil'), 'FR' => __('cremona.campaign.france')])
                        ->default('BR')
                        ->required(),
                    Textarea::make('configuration.target_locations')
                        ->label(__('cremona.campaign.target_locations'))
                        ->helperText(__('cremona.campaign.target_locations_help'))
                        ->rows(3),
                    Textarea::make('configuration.languages')
                        ->label(__('cremona.campaign.languages'))
                        ->helperText(__('cremona.campaign.languages_help'))
                        ->rows(2),
                    Repeater::make('configuration.ad_groups')
                        ->label(__('cremona.campaign.ad_groups'))
                        ->columnSpanFull()
                        ->schema([
                            TextInput::make('name')->label(__('cremona.campaign.name'))->required()->maxLength(255)->columnSpanFull(),
                            Grid::make(['default' => 1, 'md' => 2])->schema([
                                Group::make([
                                    Textarea::make('keywords')->label(__('cremona.campaign.keywords'))->helperText(__('cremona.campaign.keywords_help'))->rows(4)->required(),
                                    Textarea::make('negative_keywords')->label(__('cremona.campaign.negative_keywords'))->rows(3),
                                ]),
                                Group::make([
                                    Textarea::make('headlines')->label(__('cremona.campaign.headlines'))->rows(4)->required(),
                                    Textarea::make('descriptions')->label(__('cremona.campaign.descriptions'))->rows(3)->required(),
                                ]),
                            ]),
                        ])->columns(1)->defaultItems(0)->addActionLabel(__('cremona.campaign.add_group')),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->label(__('cremona.campaign.campaign'))->description(fn (Campaign $record): string => $record->tracking_key)->searchable()->sortable()->weight('medium'),
                TextColumn::make('channel')->label(__('common.channel'))->badge()->formatStateUsing(fn (string $state): string => match ($state) {
                    'google_ads' => 'Google Ads', 'meta_ads' => 'Meta Ads', 'linkedin_ads' => 'LinkedIn Ads', default => 'Autre',
                }),
                TextColumn::make('status')->label(__('common.status'))->badge(),
                TextColumn::make('google_ads_primary_status')
                    ->label(__('cremona.campaign.google_status'))
                    ->formatStateUsing(fn (?string $state): string => self::googleAdsPrimaryStatusLabel($state))
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'ELIGIBLE' => 'success',
                        'LEARNING', 'LIMITED' => 'warning',
                        'MISCONFIGURED', 'NOT_ELIGIBLE' => 'danger',
                        default => 'gray',
                    })
                    ->placeholder(__('cremona.campaign.to_sync'))
                    ->toggleable(),
                TextColumn::make('daily_metrics_sum_spend')->label(__('cremona.campaign.spent'))->money(fn (Campaign $record): string => $record->currency)->sortable(),
                TextColumn::make('attributed_incoming_requests_count')->label(__('cremona.campaign.site_requests'))->counts('attributedIncomingRequests')->badge()->color('success'),
                TextColumn::make('google_ads_synced_at')->label(__('common.google_updated'))->since()->placeholder(__('common.never'))->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('starts_on')->label(__('common.start'))->date('d/m/Y')->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('common.status'))->options(CampaignStatus::class),
                SelectFilter::make('channel')->label(__('common.channel'))->options(['google_ads' => 'Google Ads', 'meta_ads' => 'Meta Ads', 'linkedin_ads' => 'LinkedIn Ads', 'other' => 'Autre']),
            ])
            ->recordActions([
                ViewAction::make()->label(__('cremona.campaign.open_management')),
                Action::make('preview_google_ads')
                    ->label(__('cremona.campaign.preview_creation'))
                    ->icon(Heroicon::OutlinedEye)
                    ->visible(fn (Campaign $record): bool => $record->channel === 'google_ads' && blank($record->external_reference))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('common.close'))
                    ->modalHeading(__('cremona.campaign.preview_before_creation'))
                    ->modalDescription(__('cremona.campaign.preview_description'))
                    ->modalContent(fn (Campaign $record) => view('filament.campaigns.google-ads-preview', [
                        'preview' => app(GoogleAdsCampaignDraft::class)->preview($record),
                    ])),
                Action::make('publish_google_ads_paused')
                    ->label(__('cremona.campaign.create_paused'))
                    ->icon(Heroicon::OutlinedCloudArrowUp)
                    ->color('warning')
                    ->authorize('update')
                    ->visible(fn (Campaign $record): bool => $record->channel === 'google_ads' && blank($record->external_reference))
                    ->requiresConfirmation()
                    ->modalHeading(__('cremona.campaign.create_paused_heading'))
                    ->modalDescription(__('cremona.campaign.create_paused_description'))
                    ->modalSubmitActionLabel(__('cremona.campaign.create_paused_submit'))
                    ->action(function (Campaign $record): void {
                        $integration = OrganizationIntegration::query()
                            ->where('provider', 'google_ads')
                            ->where('name', 'reporting')
                            ->first();

                        if ($integration === null) {
                            Notification::make()->title(__('cremona.campaign.google_connection_to_prepare'))->body(__('cremona.campaign.google_connection_setup_body'))->warning()->send();

                            return;
                        }

                        try {
                            app(GoogleAdsCampaignPublisher::class)->publishPaused($record, $integration, auth()->user());
                        } catch (LogicException $exception) {
                            Notification::make()
                                ->title(__('cremona.campaign.google_creation_stopped'))
                                ->body($exception->getMessage())
                                ->danger()
                                ->persistent()
                                ->send();

                            return;
                        } catch (Throwable $exception) {
                            report($exception);

                            Notification::make()
                                ->title(__('cremona.campaign.google_creation_interrupted'))
                                ->body(__('cremona.campaign.google_creation_interrupted_body'))
                                ->danger()
                                ->persistent()
                                ->send();

                            return;
                        }

                        Notification::make()->title(__('cremona.campaign.google_campaign_created'))->body(__('cremona.campaign.google_campaign_created_body'))->success()->send();
                    }),
                Action::make('activate_google_ads')
                    ->label(__('cremona.campaign.activate_google'))
                    ->icon(Heroicon::OutlinedPlay)
                    ->color('success')
                    ->authorize('update')
                    ->visible(fn (Campaign $record): bool => $record->channel === 'google_ads'
                        && filled($record->external_reference)
                        && $record->status === CampaignStatus::Paused)
                    ->requiresConfirmation()
                    ->modalHeading(__('cremona.campaign.activate_google_heading'))
                    ->modalDescription(__('cremona.campaign.activate_google_description'))
                    ->modalSubmitActionLabel(__('cremona.campaign.activate_campaign'))
                    ->action(function (Campaign $record): void {
                        $integration = OrganizationIntegration::query()
                            ->where('provider', 'google_ads')
                            ->where('name', 'reporting')
                            ->first();

                        if ($integration === null) {
                            Notification::make()->title(__('cremona.campaign.google_connection_not_found'))->warning()->send();

                            return;
                        }

                        app(GoogleAdsCampaignPublisher::class)->activate($record, $integration, auth()->user());

                        Notification::make()->title(__('cremona.campaign.google_campaign_activated'))->success()->send();
                    }),
                Action::make('discard_google_ads_paused')
                    ->label(__('common.remove_from_google'))
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->authorize('update')
                    ->visible(fn (Campaign $record): bool => $record->channel === 'google_ads'
                        && filled($record->external_reference)
                        && $record->status === CampaignStatus::Paused
                        && ! $record->dailyMetrics()->exists())
                    ->requiresConfirmation()
                    ->modalHeading(__('cremona.campaign.remove_google_heading'))
                    ->modalDescription(__('cremona.campaign.remove_google_description'))
                    ->modalSubmitActionLabel(__('common.remove_from_google'))
                    ->action(function (Campaign $record): void {
                        $integration = OrganizationIntegration::query()
                            ->where('provider', 'google_ads')
                            ->where('name', 'reporting')
                            ->first();

                        if ($integration === null) {
                            Notification::make()->title(__('cremona.campaign.google_connection_to_prepare'))->body(__('cremona.campaign.google_connection_setup_body'))->warning()->send();

                            return;
                        }

                        try {
                            app(GoogleAdsCampaignPublisher::class)->discardPaused($record, $integration, auth()->user());
                        } catch (LogicException $exception) {
                            Notification::make()->title(__('cremona.campaign.google_removal_stopped'))->body($exception->getMessage())->danger()->persistent()->send();

                            return;
                        } catch (Throwable $exception) {
                            report($exception);
                            Notification::make()->title(__('cremona.campaign.google_removal_interrupted'))->body(__('cremona.campaign.google_removal_interrupted_body'))->danger()->persistent()->send();

                            return;
                        }

                        Notification::make()->title(__('cremona.campaign.google_campaign_removed'))->body(__('cremona.campaign.google_campaign_removed_body'))->success()->send();
                    }),
                EditAction::make(),
            ])
            ->recordUrl(fn (Campaign $record): string => self::getUrl('view', ['record' => $record]))
            ->headerActions([CreateAction::make()->label(__('common.new_campaign'))]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make(__('cremona.campaign.results_last_30_days'))
                ->description(__('cremona.campaign.results_last_30_days_description'))
                ->columnSpanFull()
                ->columns(4)
                ->schema([
                    TextEntry::make('performance_spend')
                        ->label(__('cremona.campaign.spent'))
                        ->state(fn (Campaign $record): float => static::performanceSum($record, 'spend'))
                        ->money(fn (Campaign $record): string => $record->currency)
                        ->icon(Heroicon::OutlinedBanknotes)
                        ->color('warning'),
                    TextEntry::make('performance_clicks')
                        ->label(__('cremona.dashboard.clicks'))
                        ->state(fn (Campaign $record): int => (int) static::performanceSum($record, 'clicks'))
                        ->numeric()
                        ->icon(Heroicon::OutlinedCursorArrowRays)
                        ->color('info'),
                    TextEntry::make('performance_conversions')
                        ->label(__('cremona.campaign.google_conversions'))
                        ->state(fn (Campaign $record): float => static::performanceSum($record, 'platform_conversions'))
                        ->numeric(decimalPlaces: 2)
                        ->icon(Heroicon::OutlinedArrowTrendingUp)
                        ->color('success'),
                    TextEntry::make('performance_leads')
                        ->label(__('cremona.campaign.site_requests'))
                        ->state(fn (Campaign $record): int => $record->attributedIncomingRequests()->where('received_at', '>=', now()->subDays(30))->count())
                        ->numeric()
                        ->icon(Heroicon::OutlinedInboxArrowDown)
                        ->color('success'),
                    TextEntry::make('performance_impressions')
                        ->label(__('cremona.dashboard.impressions'))
                        ->state(fn (Campaign $record): int => (int) static::performanceSum($record, 'impressions'))
                        ->numeric()
                        ->icon(Heroicon::OutlinedEye)
                        ->color('gray'),
                    TextEntry::make('performance_ctr')
                        ->label(__('common.ctr'))
                        ->state(fn (Campaign $record): string => static::rate($record, 'clicks', 'impressions'))
                        ->icon(Heroicon::OutlinedChartBar)
                        ->color('info'),
                    TextEntry::make('performance_cpc')
                        ->label(__('cremona.campaign.average_cpc'))
                        ->state(fn (Campaign $record): string => static::costPerClick($record))
                        ->icon(Heroicon::OutlinedCurrencyEuro)
                        ->color('warning'),
                    TextEntry::make('performance_converted_leads')
                        ->label(__('cremona.campaign.converted_requests'))
                        ->state(fn (Campaign $record): int => $record->attributedIncomingRequests()->where('received_at', '>=', now()->subDays(30))->whereNotNull('converted_at')->count())
                        ->numeric()
                        ->icon(Heroicon::OutlinedCheckCircle)
                        ->color('success'),
                ]),
            Section::make(__('cremona.campaign.campaign_management'))
                ->description(__('cremona.campaign.campaign_management_description'))
                ->columnSpan(2)
                ->schema([
                    TextEntry::make('name')->label(__('cremona.campaign.campaign'))->weight('semibold')->size('lg'),
                    TextEntry::make('channel')->label(__('common.channel'))->formatStateUsing(fn (string $state): string => $state === 'google_ads' ? 'Google Ads' : $state)->badge(),
                    TextEntry::make('tracking_key')->label(__('cremona.campaign.utm_key'))->copyable(),
                    TextEntry::make('starts_on')->label(__('common.start'))->date('d/m/Y')->placeholder('—'),
                    TextEntry::make('ends_on')->label(__('common.end'))->date('d/m/Y')->placeholder('—'),
                    TextEntry::make('planned_budget')->label(__('cremona.campaign.planned_budget'))->money(fn (Campaign $record): string => $record->currency)->placeholder('—'),
                ])->columns(3),
            Section::make(__('cremona.campaign.google_observed_status'))
                ->columnSpan(1)
                ->schema([
                    TextEntry::make('google_ads_primary_status')
                        ->label(__('common.state'))
                        ->formatStateUsing(fn (?string $state): string => self::googleAdsPrimaryStatusLabel($state))
                        ->badge()
                        ->color(fn (?string $state): string => match ($state) {
                            'ELIGIBLE' => 'success',
                            'LEARNING', 'LIMITED' => 'warning',
                            'MISCONFIGURED', 'NOT_ELIGIBLE' => 'danger',
                            default => 'gray',
                        })
                        ->placeholder(__('cremona.campaign.to_sync')),
                    TextEntry::make('google_ads_primary_status_reasons')
                        ->label(__('common.details'))
                        ->formatStateUsing(fn (mixed $state): string => static::googleAdsPrimaryStatusReasonLabel($state)),
                    TextEntry::make('google_ads_synced_at')->label(__('cremona.campaign.last_observation'))->dateTime('d/m/Y H:i')->timezone(fn (): string => static::getOrganizationTimezone())->placeholder(__('common.never')),
                    TextEntry::make('google_ads_serving_status')->label(__('cremona.campaign.serving'))->formatStateUsing(fn (?string $state): string => static::googleAdsServingStatusLabel($state))->placeholder('—'),
                    TextEntry::make('google_ads_bidding_status')->label(__('cremona.campaign.bidding'))->formatStateUsing(fn (?string $state): string => static::googleAdsBiddingStatusLabel($state))->placeholder('—'),
                ]),
            Section::make(__('cremona.campaign.google_observed_configuration'))
                ->description(__('cremona.campaign.google_observed_configuration_description'))
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
        return __('cremona.campaign.google_primary_status.'.strtolower($status ?? 'to_sync'));
    }

    private static function googleAdsServingStatusLabel(?string $status): string
    {
        return __('cremona.campaign.google_serving_status.'.strtolower($status ?? 'unknown'));
    }

    private static function googleAdsBiddingStatusLabel(?string $status): string
    {
        return __('cremona.campaign.google_bidding_status.'.strtolower($status ?? 'unknown'));
    }

    private static function googleAdsPrimaryStatusReasonLabel(mixed $reasons): string
    {
        $reasons = is_array($reasons) ? $reasons : [$reasons];
        $labels = collect($reasons)->filter()->map(fn (string $reason): string => __('cremona.campaign.google_status_reason.'.strtolower($reason)))->unique()->values();

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
