<?php

namespace App\Filament\Widgets;

use App\Enums\AppointmentStatus;
use App\Enums\CampaignStatus;
use App\Enums\ConversationStatus;
use App\Enums\CrmTaskStatus;
use App\Enums\IncomingRequestStatus;
use App\Enums\OrganizationPermission;
use App\Enums\QuoteStatus;
use App\Filament\Resources\Appointments\AppointmentResource;
use App\Filament\Resources\Campaigns\CampaignResource;
use App\Filament\Resources\Conversations\ConversationResource;
use App\Filament\Resources\CrmTasks\CrmTaskResource;
use App\Filament\Resources\IncomingRequests\IncomingRequestResource;
use App\Filament\Resources\Quotes\QuoteResource;
use App\Models\Appointment;
use App\Models\Campaign;
use App\Models\Conversation;
use App\Models\CrmTask;
use App\Models\IncomingRequest;
use App\Models\Quote;
use App\Tenancy\OrganizationContext;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class CrmOverview extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected ?string $pollingInterval = null;

    protected static ?int $sort = 10;

    protected ?string $heading = 'Priorités du jour';

    public static function canView(): bool
    {
        $organization = app(OrganizationContext::class)->current();
        $user = auth()->user();

        return $organization !== null
            && $user !== null
            && $user->hasOrganizationPermission(OrganizationPermission::ViewCrm, $organization);
    }

    protected function getStats(): array
    {
        $organization = app(OrganizationContext::class)->require();
        $timezone = $organization->timezone();
        $now = now($timezone);
        $endOfDay = $now->copy()->endOfDay()->utc();

        $new = IncomingRequest::query()
            ->where('status', IncomingRequestStatus::New)
            ->count();
        $unread = IncomingRequest::query()
            ->where('status', '!=', IncomingRequestStatus::Closed)
            ->whereNull('read_at')
            ->count();
        $conversations = Conversation::query()
            ->where('status', ConversationStatus::Open)
            ->whereNotNull('last_inbound_at')
            ->where(fn (Builder $query): Builder => $query
                ->whereNull('last_outbound_at')
                ->orWhereColumn('last_inbound_at', '>', 'last_outbound_at'))
            ->count();
        $tasksDue = CrmTask::query()
            ->whereIn('status', [CrmTaskStatus::Open, CrmTaskStatus::InProgress])
            ->whereNotNull('due_at')
            ->where('due_at', '<=', $endOfDay)
            ->count();
        $overdueTasks = CrmTask::query()
            ->whereIn('status', [CrmTaskStatus::Open, CrmTaskStatus::InProgress])
            ->where('due_at', '<', now())
            ->count();
        $appointmentsToday = Appointment::query()
            ->where('status', AppointmentStatus::Scheduled)
            ->whereBetween('starts_at', [now(), $endOfDay])
            ->count();
        $quotesToFollow = Quote::query()
            ->where('status', QuoteStatus::Sent)
            ->count();
        $expiredQuotes = Quote::query()
            ->where('status', QuoteStatus::Sent)
            ->whereDate('valid_until', '<', $now->toDateString())
            ->count();
        $campaignsToCheck = Campaign::query()
            ->where('status', CampaignStatus::Active)
            ->where('channel', 'google_ads')
            ->whereNotNull('external_reference')
            ->where(fn (Builder $query): Builder => $query
                ->whereNull('google_ads_synced_at')
                ->orWhere('google_ads_synced_at', '<', now()->subDay()))
            ->count();
        $lastCampaignSync = Campaign::query()
            ->where('channel', 'google_ads')
            ->max('google_ads_synced_at');
        $campaignSource = $lastCampaignSync === null
            ? 'Google Ads · jamais synchronisé'
            : 'Google Ads · synchro '.Carbon::parse($lastCampaignSync)->setTimezone($timezone)->format('d/m H:i');

        return [
            Stat::make('Nouvelles demandes', $new)
                ->description("{$unread} non lue".($unread > 1 ? 's' : '').' · Voir les demandes')
                ->descriptionIcon(Heroicon::OutlinedEnvelopeOpen)
                ->color($new > 0 ? 'info' : 'gray')
                ->url(IncomingRequestResource::getUrl('index', ['tab' => 'new'])),
            Stat::make('Correspondances à traiter', $conversations)
                ->description('Dernier message reçu · Voir les correspondances')
                ->descriptionIcon(Heroicon::OutlinedChatBubbleLeftRight)
                ->color($conversations > 0 ? 'warning' : 'gray')
                ->url(ConversationResource::getUrl('index', ['tab' => 'open'])),
            Stat::make('Tâches à échéance', $tasksDue)
                ->description("{$overdueTasks} en retard · Voir les tâches")
                ->descriptionIcon(Heroicon::OutlinedCheckCircle)
                ->color($overdueTasks > 0 ? 'danger' : ($tasksDue > 0 ? 'warning' : 'gray'))
                ->url(CrmTaskResource::getUrl('index', ['tab' => 'due'])),
            Stat::make('Rendez-vous aujourd’hui', $appointmentsToday)
                ->description('À venir · Voir les rendez-vous')
                ->descriptionIcon(Heroicon::OutlinedCalendarDays)
                ->color($appointmentsToday > 0 ? 'info' : 'gray')
                ->url(AppointmentResource::getUrl('index', ['tab' => 'today'])),
            Stat::make('Devis à suivre', $quotesToFollow)
                ->description("{$expiredQuotes} hors validité · Voir les devis")
                ->descriptionIcon(Heroicon::OutlinedDocumentCurrencyEuro)
                ->color($expiredQuotes > 0 ? 'danger' : ($quotesToFollow > 0 ? 'warning' : 'gray'))
                ->url(QuoteResource::getUrl('index', ['tab' => 'follow_up'])),
            Stat::make('Campagnes à vérifier', $campaignsToCheck)
                ->description($campaignSource.' · Voir les campagnes')
                ->descriptionIcon(Heroicon::OutlinedMegaphone)
                ->color($campaignsToCheck > 0 ? 'warning' : 'gray')
                ->url(CampaignResource::getUrl('index', ['tab' => 'attention'])),
        ];
    }
}
