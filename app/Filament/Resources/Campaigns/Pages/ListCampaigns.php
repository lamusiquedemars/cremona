<?php

namespace App\Filament\Resources\Campaigns\Pages;

use App\Enums\CampaignStatus;
use App\Filament\Resources\Campaigns\CampaignResource;
use App\Models\Campaign;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListCampaigns extends ListRecords
{
    protected static string $resource = CampaignResource::class;

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Toutes'),
            'attention' => Tab::make('À vérifier')
                ->badge(fn (): int => $this->attentionQuery(Campaign::query())->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query): Builder => $this->attentionQuery($query)),
            'active' => Tab::make('Actives')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', CampaignStatus::Active)),
            'draft' => Tab::make('Brouillons')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', CampaignStatus::Draft)),
            'archived' => Tab::make('Archivées')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', CampaignStatus::Archived)),
        ];
    }

    private function attentionQuery(Builder $query): Builder
    {
        return $query
            ->where('status', CampaignStatus::Active)
            ->where('channel', 'google_ads')
            ->whereNotNull('external_reference')
            ->where(fn (Builder $query): Builder => $query
                ->whereNull('google_ads_synced_at')
                ->orWhere('google_ads_synced_at', '<', now()->subDay()));
    }
}
