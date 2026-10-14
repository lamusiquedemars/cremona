<?php

namespace App\Filament\Pages;

use App\Enums\ConversationStatus;
use App\Enums\CrmTaskStatus;
use App\Enums\IncomingRequestStatus;
use App\Enums\OrganizationPermission;
use App\Filament\Resources\Conversations\ConversationResource;
use App\Filament\Resources\CrmTasks\CrmTaskResource;
use App\Filament\Resources\IncomingRequests\IncomingRequestResource;
use App\Models\Conversation;
use App\Models\CrmTask;
use App\Models\IncomingRequest;
use App\Models\User;
use App\Services\OrganizationModuleAccess;
use App\Services\OrganizationPresentation;
use App\Tenancy\OrganizationContext;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\WithPagination;

class ClientWorkQueue extends Page
{
    use WithPagination;

    protected string $view = 'filament.pages.client-work-queue';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Suivi client';

    protected static ?string $navigationLabel = 'À traiter';

    protected static ?int $navigationSort = 5;

    protected Width|string|null $maxContentWidth = Width::Full;

    public string $type = 'all';

    public static function canAccess(): bool
    {
        $organization = app(OrganizationContext::class)->current();
        $user = auth()->user();

        return $organization !== null
            && $user instanceof User
            && $user->hasOrganizationPermission(OrganizationPermission::ViewCrm, $organization)
            && app(OrganizationModuleAccess::class)->enabled('crm', $organization);
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return app(OrganizationPresentation::class)->navigationGroupLabel('customer_follow_up', 'Suivi client');
    }

    public function getTitle(): string
    {
        return 'À traiter';
    }

    public function selectType(string $type): void
    {
        abort_unless(in_array($type, ['all', 'request', 'conversation', 'task'], true), 404);

        $this->type = $type;
        $this->resetPage('clientWorkPage');
    }

    /** @return LengthAwarePaginator<object> */
    public function items(): LengthAwarePaginator
    {
        $query = match ($this->type) {
            'request' => $this->requestsQuery(),
            'conversation' => $this->conversationsQuery(),
            'task' => $this->tasksQuery(),
            default => $this->requestsQuery()
                ->unionAll($this->conversationsQuery())
                ->unionAll($this->tasksQuery()),
        };

        return DB::query()
            ->fromSub($query, 'client_work_items')
            ->orderByDesc('sort_at')
            ->paginate(25, ['*'], 'clientWorkPage');
    }

    public function itemUrl(object $item): string
    {
        return match ($item->item_type) {
            'conversation' => ConversationResource::getUrl('view', ['record' => $item->id]),
            'task' => CrmTaskResource::getUrl('view', ['record' => $item->id]),
            default => IncomingRequestResource::getUrl('view', ['record' => $item->id]),
        };
    }

    public function itemTypeLabel(object $item): string
    {
        return match ($item->item_type) {
            'conversation' => 'Message à répondre',
            'task' => 'Tâche',
            default => 'Demande',
        };
    }

    private function requestsQuery(): Builder
    {
        return IncomingRequest::query()
            ->select('incoming_requests.id')
            ->selectRaw("'request' as item_type")
            ->selectRaw("COALESCE(NULLIF(incoming_requests.subject, ''), 'Demande sans objet') as title")
            ->selectRaw("COALESCE(incoming_requests.name_snapshot, incoming_requests.email_snapshot, incoming_requests.phone_snapshot, 'Contact à préciser') as contact")
            ->selectRaw('incoming_requests.status as state')
            ->selectRaw('incoming_requests.message as summary')
            ->selectRaw('incoming_requests.received_at as sort_at')
            ->whereIn('incoming_requests.status', [
                IncomingRequestStatus::New,
                IncomingRequestStatus::InProgress,
                IncomingRequestStatus::Qualified,
            ]);
    }

    private function conversationsQuery(): Builder
    {
        return Conversation::query()
            ->leftJoin('people', 'people.id', '=', 'conversations.person_id')
            ->leftJoin('companies', 'companies.id', '=', 'conversations.company_id')
            ->select('conversations.id')
            ->selectRaw("'conversation' as item_type")
            ->selectRaw("COALESCE(NULLIF(conversations.subject, ''), 'Message sans objet') as title")
            ->selectRaw("COALESCE(people.display_name, companies.name, 'Contact à rapprocher') as contact")
            ->selectRaw('conversations.status as state')
            ->selectRaw("'Réponse attendue' as summary")
            ->selectRaw('conversations.last_inbound_at as sort_at')
            ->where('conversations.status', ConversationStatus::Open)
            ->whereNotNull('conversations.last_inbound_at')
            ->where(fn (Builder $query): Builder => $query
                ->whereNull('conversations.last_outbound_at')
                ->orWhereColumn('conversations.last_inbound_at', '>', 'conversations.last_outbound_at'));
    }

    private function tasksQuery(): Builder
    {
        return CrmTask::query()
            ->leftJoin('people', 'people.id', '=', 'crm_tasks.person_id')
            ->leftJoin('companies', 'companies.id', '=', 'crm_tasks.company_id')
            ->select('crm_tasks.id')
            ->selectRaw("'task' as item_type")
            ->selectRaw('crm_tasks.title as title')
            ->selectRaw("COALESCE(people.display_name, companies.name, 'Sans client lié') as contact")
            ->selectRaw('crm_tasks.status as state')
            ->selectRaw("COALESCE(crm_tasks.description, 'Action à réaliser') as summary")
            ->selectRaw('COALESCE(crm_tasks.due_at, crm_tasks.created_at) as sort_at')
            ->whereIn('crm_tasks.status', [CrmTaskStatus::Open, CrmTaskStatus::InProgress]);
    }
}
