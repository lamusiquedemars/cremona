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
use App\Services\OrganizationModuleAccess;
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

    public static function canView(): bool
    {
        $organization = app(OrganizationContext::class)->current();
        $user = auth()->user();

        return $organization !== null
            && $user !== null
            && app(OrganizationModuleAccess::class)->anyEnabled([
                'crm',
                'appointments',
                'quotes',
                'marketing',
            ], $organization)
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
            ? __('cremona.dashboard.campaign_never_synced')
            : __('cremona.dashboard.campaign_synced', ['date' => Carbon::parse($lastCampaignSync)->setTimezone($timezone)->format('d/m H:i')]);

        $moduleAccess = app(OrganizationModuleAccess::class);
        $stats = [];

        if ($moduleAccess->enabled('crm', $organization)) {
            $stats[] = Stat::make(__('cremona.dashboard.new_requests'), $new)
                ->description(trans_choice('cremona.dashboard.new_requests_description', $unread, ['count' => $unread]))
                ->descriptionIcon(Heroicon::OutlinedEnvelopeOpen)
                ->color($new > 0 ? 'info' : 'gray')
                ->url(IncomingRequestResource::getUrl('index', ['tab' => 'new']));
            $stats[] = Stat::make(__('cremona.dashboard.conversations'), $conversations)
                ->description(__('cremona.dashboard.conversations_description'))
                ->descriptionIcon(Heroicon::OutlinedChatBubbleLeftRight)
                ->color($conversations > 0 ? 'warning' : 'gray')
                ->url(ConversationResource::getUrl('index', ['tab' => 'open']));
            $stats[] = Stat::make(__('cremona.dashboard.tasks_due'), $tasksDue)
                ->description(trans_choice('cremona.dashboard.tasks_due_description', $overdueTasks, ['count' => $overdueTasks]))
                ->descriptionIcon(Heroicon::OutlinedCheckCircle)
                ->color($overdueTasks > 0 ? 'danger' : ($tasksDue > 0 ? 'warning' : 'gray'))
                ->url(CrmTaskResource::getUrl('index', ['tab' => 'due']));
        }

        if ($moduleAccess->enabled('appointments', $organization)) {
            $stats[] = Stat::make(__('cremona.dashboard.appointments_today'), $appointmentsToday)
                ->description(__('cremona.dashboard.appointments_today_description'))
                ->descriptionIcon(Heroicon::OutlinedCalendarDays)
                ->color($appointmentsToday > 0 ? 'info' : 'gray')
                ->url(AppointmentResource::getUrl('index', ['tab' => 'today']));
        }

        if ($moduleAccess->enabled('quotes', $organization)) {
            $stats[] = Stat::make(__('cremona.dashboard.quotes_to_follow'), $quotesToFollow)
                ->description(trans_choice('cremona.dashboard.quotes_to_follow_description', $expiredQuotes, ['count' => $expiredQuotes]))
                ->descriptionIcon(Heroicon::OutlinedDocumentCurrencyEuro)
                ->color($expiredQuotes > 0 ? 'danger' : ($quotesToFollow > 0 ? 'warning' : 'gray'))
                ->url(QuoteResource::getUrl('index', ['tab' => 'follow_up']));
        }

        if ($moduleAccess->enabled('marketing', $organization)) {
            $stats[] = Stat::make(__('cremona.dashboard.campaigns_to_check'), $campaignsToCheck)
                ->description(__('cremona.dashboard.campaigns_to_check_description', ['source' => $campaignSource]))
                ->descriptionIcon(Heroicon::OutlinedMegaphone)
                ->color($campaignsToCheck > 0 ? 'warning' : 'gray')
                ->url(CampaignResource::getUrl('index', ['tab' => 'attention']));
        }

        return $stats;
    }

    protected function getHeading(): ?string
    {
        return __('cremona.dashboard.priorities');
    }
}
