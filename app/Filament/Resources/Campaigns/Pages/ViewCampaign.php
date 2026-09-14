<?php

namespace App\Filament\Resources\Campaigns\Pages;

use App\Filament\Resources\Campaigns\CampaignResource;
use App\Filament\Resources\GoogleAdsConnections\GoogleAdsConnectionResource;
use App\Models\OrganizationIntegration;
use App\Services\GoogleAdsCampaignConfigurationAdopter;
use App\Services\GoogleAdsCampaignKeywordPublisher;
use App\Services\GoogleAdsReportingClient;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use LogicException;
use Throwable;

class ViewCampaign extends ViewRecord
{
    protected static string $resource = CampaignResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        if ($this->needsGoogleAdsRefresh()) {
            $this->synchronizeGoogleAds(false);
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sync_google_ads')
                ->label(__('cremona.campaign.refresh_now'))
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('success')
                ->visible(fn (): bool => $this->record->channel === 'google_ads' && filled($this->record->external_reference))
                ->authorize('update')
                ->action(function (): void {
                    $this->synchronizeGoogleAds(true);
                }),
            EditAction::make()->label(fn (): string => filled($this->record->external_reference)
                ? __('cremona.campaign.edit_preparation')
                : __('cremona.campaign.edit_draft')),
            Action::make('adopt_google_keywords')
                ->label(__('cremona.campaign.adopt_google_keywords'))
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('warning')
                ->visible(fn (): bool => filled($this->record->google_ads_configuration))
                ->authorize('update')
                ->requiresConfirmation()
                ->modalHeading(__('cremona.campaign.adopt_google_keywords_heading'))
                ->modalDescription(__('cremona.campaign.adopt_google_keywords_description'))
                ->action(function (GoogleAdsCampaignConfigurationAdopter $adopter): void {
                    $count = $adopter->adoptKeywords($this->record, auth()->user());
                    $this->record->refresh();
                    Notification::make()->title(__('cremona.campaign.preparation_updated'))->body(trans_choice('cremona.campaign.groups_adopted', $count, ['count' => $count]))->success()->send();
                }),
            Action::make('apply_prepared_keywords')
                ->label(__('cremona.campaign.apply_keywords_to_google'))
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->color('danger')
                ->visible(fn (): bool => $this->record->channel === 'google_ads'
                    && filled($this->record->external_reference)
                    && filled($this->record->google_ads_configuration))
                ->authorize('update')
                ->requiresConfirmation()
                ->modalHeading(__('cremona.campaign.apply_keywords_heading'))
                ->modalDescription(__('cremona.campaign.apply_keywords_description'))
                ->action(function (GoogleAdsCampaignKeywordPublisher $publisher): void {
                    $integration = $this->googleAdsIntegration();

                    if ($integration === null) {
                        Notification::make()
                            ->title(__('cremona.campaign.google_connection_to_prepare'))
                            ->body(__('cremona.campaign.google_update_connection_body'))
                            ->warning()
                            ->persistent()
                            ->send();

                        return;
                    }

                    try {
                        $result = $publisher->apply($this->record, $integration, auth()->user());
                        $this->record->refresh();
                    } catch (LogicException $exception) {
                        Notification::make()->title(__('cremona.campaign.google_update_stopped'))->body($exception->getMessage())->danger()->persistent()->send();

                        return;
                    } catch (Throwable $exception) {
                        report($exception);
                        Notification::make()->title(__('cremona.campaign.google_update_interrupted'))->body(__('cremona.campaign.google_update_interrupted_body'))->danger()->persistent()->send();

                        return;
                    }

                    $this->synchronizeGoogleAds(false);
                    $this->record->refresh();
                    $message = $result['created'].' ajout(s), '.$result['removed'].' retrait(s), dans '.$result['groups'].' groupe(s) correspondant(s).';
                    Notification::make()->title(__('cremona.campaign.google_keywords_updated'))->body($message)->success()->send();
                }),
        ];
    }

    private function needsGoogleAdsRefresh(): bool
    {
        return $this->record->channel === 'google_ads'
            && filled($this->record->external_reference)
            && Gate::allows('update', $this->record)
            && ($this->record->google_ads_synced_at === null || $this->record->google_ads_synced_at->lt(now()->subMinutes(15)));
    }

    private function synchronizeGoogleAds(bool $announce): void
    {
        $integration = $this->googleAdsIntegration();

        if ($integration === null || ! GoogleAdsConnectionResource::isReady($integration->credentials)) {
            if ($announce) {
                Notification::make()
                    ->title(__('cremona.campaign.google_connection_to_prepare'))
                    ->body(__('cremona.campaign.google_sync_connection_body'))
                    ->warning()
                    ->persistent()
                    ->send();
            }

            return;
        }

        try {
            app(GoogleAdsReportingClient::class)->syncCampaign($this->record, $integration);
            $this->record->refresh();
        } catch (LogicException $exception) {
            if ($announce) {
                Notification::make()->title(__('cremona.campaign.google_sync_stopped'))->body($exception->getMessage())->danger()->persistent()->send();
            }

            return;
        } catch (Throwable $exception) {
            report($exception);

            if ($announce) {
                Notification::make()->title(__('cremona.campaign.google_sync_interrupted'))->body(__('cremona.campaign.google_sync_interrupted_body'))->danger()->persistent()->send();
            }

            return;
        }

        if ($announce) {
            Notification::make()->title(__('cremona.campaign.google_campaign_refreshed'))->body(__('cremona.campaign.google_campaign_refreshed_body'))->success()->send();
        }
    }

    private function googleAdsIntegration(): ?OrganizationIntegration
    {
        return OrganizationIntegration::query()
            ->where('provider', 'google_ads')
            ->where('name', 'reporting')
            ->first();
    }
}
