<?php

namespace App\Filament\Pages;

use App\Enums\ConversationStatus;
use App\Enums\CrmTaskStatus;
use App\Enums\IncomingRequestStatus;
use App\Enums\OrganizationPermission;
use App\Filament\Resources\Conversations\ConversationResource;
use App\Filament\Resources\CrmTasks\CrmTaskResource;
use App\Filament\Resources\IncomingRequests\IncomingRequestResource;
use App\Models\Company;
use App\Models\Conversation;
use App\Models\CrmTask;
use App\Models\IncomingRequest;
use App\Models\Person;
use App\Models\User;
use App\Services\OrganizationModuleAccess;
use App\Services\OrganizationPresentation;
use App\Support\EmailHeaderDecoder;
use App\Tenancy\OrganizationContext;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
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
            'conversation' => ConversationResource::getUrl('view', ['record' => $item->record_key]),
            'task' => CrmTaskResource::getUrl('view', ['record' => $item->record_key]),
            default => IncomingRequestResource::getUrl('view', ['record' => $item->record_key]),
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

    public function itemTitle(object $item): string
    {
        return EmailHeaderDecoder::text($item->title) ?? 'Sans objet';
    }

    public function itemDateLabel(object $item): string
    {
        return match ($item->item_type) {
            'task' => 'À faire le',
            'conversation' => 'Reçu le',
            default => 'Reçue le',
        };
    }

    private function requestsQuery(): Builder
    {
        $table = (new IncomingRequest)->getTable();
        $rawTable = $this->prefixedTable(new IncomingRequest);

        return IncomingRequest::query()
            ->select("{$table}.id")
            ->select("{$table}.public_id as record_key")
            ->selectRaw("'request' as item_type")
            ->selectRaw("COALESCE(NULLIF({$rawTable}.subject, ''), 'Demande sans objet') as title")
            ->selectRaw("COALESCE({$rawTable}.name_snapshot, {$rawTable}.email_snapshot, {$rawTable}.phone_snapshot, 'Contact à préciser') as contact")
            ->selectRaw("{$rawTable}.status as state")
            ->selectRaw("{$rawTable}.message as summary")
            ->selectRaw("{$rawTable}.received_at as sort_at")
            ->whereIn("{$table}.status", [
                IncomingRequestStatus::New,
                IncomingRequestStatus::InProgress,
                IncomingRequestStatus::Qualified,
            ]);
    }

    private function conversationsQuery(): Builder
    {
        $table = (new Conversation)->getTable();
        $people = (new Person)->getTable();
        $companies = (new Company)->getTable();
        $rawTable = $this->prefixedTable(new Conversation);
        $rawPeople = $this->prefixedTable(new Person);
        $rawCompanies = $this->prefixedTable(new Company);

        return Conversation::query()
            ->leftJoin($people, "{$people}.id", '=', "{$table}.person_id")
            ->leftJoin($companies, "{$companies}.id", '=', "{$table}.company_id")
            ->select("{$table}.id")
            ->select("{$table}.public_id as record_key")
            ->selectRaw("'conversation' as item_type")
            ->selectRaw("COALESCE(NULLIF({$rawTable}.subject, ''), 'Message sans objet') as title")
            ->selectRaw("COALESCE({$rawPeople}.display_name, {$rawCompanies}.name, 'Contact à rapprocher') as contact")
            ->selectRaw("{$rawTable}.status as state")
            ->selectRaw("'Réponse attendue' as summary")
            ->selectRaw("{$rawTable}.last_inbound_at as sort_at")
            ->where("{$table}.status", ConversationStatus::Open)
            ->whereNotNull("{$table}.last_inbound_at")
            ->where(fn (Builder $query): Builder => $query
                ->whereNull("{$table}.last_outbound_at")
                ->orWhereColumn("{$table}.last_inbound_at", '>', "{$table}.last_outbound_at"));
    }

    private function tasksQuery(): Builder
    {
        $table = (new CrmTask)->getTable();
        $people = (new Person)->getTable();
        $companies = (new Company)->getTable();
        $rawTable = $this->prefixedTable(new CrmTask);
        $rawPeople = $this->prefixedTable(new Person);
        $rawCompanies = $this->prefixedTable(new Company);

        return CrmTask::query()
            ->leftJoin($people, "{$people}.id", '=', "{$table}.person_id")
            ->leftJoin($companies, "{$companies}.id", '=', "{$table}.company_id")
            ->select("{$table}.id")
            ->select("{$table}.public_id as record_key")
            ->selectRaw("'task' as item_type")
            ->selectRaw("{$rawTable}.title as title")
            ->selectRaw("COALESCE({$rawPeople}.display_name, {$rawCompanies}.name, 'Sans client lié') as contact")
            ->selectRaw("{$rawTable}.status as state")
            ->selectRaw("COALESCE({$rawTable}.description, 'Action à réaliser') as summary")
            ->selectRaw("COALESCE({$rawTable}.due_at, {$rawTable}.created_at) as sort_at")
            ->whereIn("{$table}.status", [CrmTaskStatus::Open, CrmTaskStatus::InProgress]);
    }

    private function prefixedTable(Model $model): string
    {
        $table = $model->getTable();
        $prefix = $model->getConnection()->getTablePrefix();

        return $prefix !== '' && ! str_starts_with($table, $prefix) ? $prefix.$table : $table;
    }
}
