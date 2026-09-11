<?php

namespace App\Filament\Widgets;

use App\Enums\AppointmentStatus;
use App\Enums\ConversationStatus;
use App\Enums\CrmTaskStatus;
use App\Enums\IncomingRequestStatus;
use App\Enums\OrganizationPermission;
use App\Enums\QuoteStatus;
use App\Models\Appointment;
use App\Models\Campaign;
use App\Models\Conversation;
use App\Models\CrmTask;
use App\Models\IncomingRequest;
use App\Models\Person;
use App\Models\Quote;
use App\Services\OrganizationModuleAccess;
use App\Tenancy\OrganizationContext;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class DashboardHomeSummary extends Widget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.dashboard-home-summary';

    public static function canView(): bool
    {
        $organization = app(OrganizationContext::class)->current();
        $user = auth()->user();

        return $organization !== null
            && $user !== null
            && $user->hasOrganizationPermission(OrganizationPermission::ViewCrm, $organization);
    }

    /** @return array{first_name: string, date: string, status: string, overview: array<int, array{label: string, value: string, icon: Heroicon}>} */
    protected function getViewData(): array
    {
        $organization = app(OrganizationContext::class)->require();
        $moduleAccess = app(OrganizationModuleAccess::class);
        $timezone = $organization->timezone();
        $todayEnd = now($timezone)->endOfDay()->utc();

        $crmEnabled = $moduleAccess->enabled('crm', $organization);
        $appointmentsEnabled = $moduleAccess->enabled('appointments', $organization);

        $pendingRequests = $crmEnabled
            ? IncomingRequest::query()
                ->whereIn('status', [IncomingRequestStatus::New, IncomingRequestStatus::InProgress, IncomingRequestStatus::Qualified])
                ->count()
            : 0;
        $pendingConversations = $crmEnabled
            ? Conversation::query()
                ->where('status', ConversationStatus::Open)
                ->whereNotNull('last_inbound_at')
                ->where(fn (Builder $query): Builder => $query
                    ->whereNull('last_outbound_at')
                    ->orWhereColumn('last_inbound_at', '>', 'last_outbound_at'))
                ->count()
            : 0;
        $tasksDue = $crmEnabled
            ? CrmTask::query()
                ->whereIn('status', [CrmTaskStatus::Open, CrmTaskStatus::InProgress])
                ->whereNotNull('due_at')
                ->where('due_at', '<=', $todayEnd)
                ->count()
            : 0;
        $appointmentsToday = $appointmentsEnabled
            ? Appointment::query()
                ->where('status', AppointmentStatus::Scheduled)
                ->whereBetween('starts_at', [now(), $todayEnd])
                ->count()
            : 0;
        $actions = $pendingRequests + $pendingConversations + $tasksDue + $appointmentsToday;

        $overview = [];

        if ($crmEnabled) {
            $contacts = Person::query()->count();
            $requestsThisMonth = IncomingRequest::query()
                ->where('received_at', '>=', now($timezone)->subDays(30))
                ->count();
            $overview[] = [
                'label' => 'Suivi client',
                'value' => $contacts.' contact'.($contacts > 1 ? 's' : '').' · '.$requestsThisMonth.' demande'.($requestsThisMonth > 1 ? 's' : '').' en 30 j',
                'icon' => Heroicon::OutlinedUsers,
            ];
        }

        if ($appointmentsEnabled) {
            $upcomingAppointments = Appointment::query()
                ->where('status', AppointmentStatus::Scheduled)
                ->where('starts_at', '>=', now())
                ->count();
            $overview[] = [
                'label' => 'Rendez-vous',
                'value' => $upcomingAppointments.' à venir',
                'icon' => Heroicon::OutlinedCalendarDays,
            ];
        }

        if ($moduleAccess->enabled('quotes', $organization)) {
            $sentQuotes = Quote::query()->where('status', QuoteStatus::Sent)->count();
            $overview[] = [
                'label' => 'Devis',
                'value' => $sentQuotes.' envoyé'.($sentQuotes > 1 ? 's' : ''),
                'icon' => Heroicon::OutlinedDocumentCurrencyEuro,
            ];
        }

        if ($moduleAccess->enabled('marketing', $organization)) {
            $activeCampaigns = Campaign::query()->where('status', 'active')->count();
            $lastSync = Campaign::query()->whereNotNull('google_ads_synced_at')->max('google_ads_synced_at');
            $synchronization = $lastSync === null
                ? 'à actualiser'
                : 'actualisée '.Carbon::parse($lastSync)->setTimezone($timezone)->diffForHumans();
            $overview[] = [
                'label' => 'Campagnes',
                'value' => $activeCampaigns.' active'.($activeCampaigns > 1 ? 's' : '').' · '.$synchronization,
                'icon' => Heroicon::OutlinedMegaphone,
            ];
        }

        $name = trim((string) (auth()->user()?->name));
        $firstName = str($name)->before(' ')->trim()->toString();

        return [
            'first_name' => $firstName !== '' ? $firstName : 'bonjour',
            'date' => now($timezone)->locale('fr')->translatedFormat('l d F'),
            'status' => $actions === 0
                ? 'Aucune action urgente aujourd’hui.'
                : $actions.' action'.($actions > 1 ? 's' : '').' à traiter aujourd’hui.',
            'overview' => $overview,
        ];
    }
}
